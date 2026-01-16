<?php
require_once 'GradeComparativaRepo.php';

class GradeComparativaService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    /**
     * Retorna JSON para o Vue.
     * Estrutura:
     * - cabecalho: fornecedores
     * - linhas: itens (cada linha com celulas por fornecedor)
     * - total_raw/total_fmt
     */
    public function montarGradeParaFront($idProcesso) {
        return $this->logicaDeMontagem((int)$idProcesso);
    }

    /**
     * Salva atende/justificativa, consolida vencedores e grava grade_de_custos + valor_final_processo.
     *
     * @param int $idProcesso
     * @param array $ofertas [{ oferta_id, atende, justificativa_da_recusa }]
     */
    public function consolidarProcesso($idProcesso, $ofertas = []) {
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) {
            throw new Exception('ID do processo invalido.');
        }

        // 1) Persiste atende/justificativa (se veio do front)
        if (!is_array($ofertas)) $ofertas = [];
        foreach ($ofertas as $o) {
            $ofertaId = isset($o['oferta_id']) ? (int)$o['oferta_id'] : 0;
            if ($ofertaId <= 0) continue;

            $atende = !empty($o['atende']);
            $just = isset($o['justificativa_da_recusa']) ? trim((string)$o['justificativa_da_recusa']) : '';

            if ($atende) {
                $just = null;
            } else {
                if ($just === '') {
                    throw new Exception('Existe cotacao marcada como NAO atende sem justificativa.');
                }
                if (mb_strlen($just) > 50) {
                    $just = mb_substr($just, 0, 50);
                }
            }

            $this->repo->atualizarAtendeJustificativa($ofertaId, $atende, $just);
        }

        // 2) Recalcula no backend (seguranca)
        $dados = $this->logicaDeMontagem($idProcesso);

        // 3) Grava valor final do processo
        $total = isset($dados['total_raw']) ? (float)$dados['total_raw'] : 0.0;
        $this->repo->salvarValorFinal($idProcesso, $total);

        // 4) Grava vencedores na grade_de_custos (desconsiderando atende = 0)
        $this->repo->limparGradeDeCustos($idProcesso);

        $rows = [];
        foreach (($dados['linhas'] ?? []) as $linha) {
            $idItem = isset($linha['id_item']) ? (int)$linha['id_item'] : 0;
            if ($idItem <= 0) continue;

            foreach (($linha['celulas'] ?? []) as $fid => $c) {
                $status = $c['status'] ?? '';
                $valor = isset($c['valor']) ? (float)$c['valor'] : 0.0;
                $atende = !empty($c['atende']);
                $ofertaId = isset($c['oferta_id']) ? (int)$c['oferta_id'] : 0;

                if ($ofertaId <= 0) continue;
                if (!$atende) continue;
                if ($valor <= 0) continue;

                // vencedores: winner e tie
                if ($status === 'winner' || $status === 'tie') {
                    $rows[] = [
                        'id_fornecedor_senior' => (int)$fid,
                        'id_item' => $idItem,
                        'valor_cotado' => $valor,
                    ];
                }
            }
        }

        $gravados = $this->repo->inserirGradeDeCustos($idProcesso, $rows);

        return [
            'sucesso' => true,
            'valor_gravado' => $dados['total_fmt'] ?? '0,00',
            'itens_gravados' => $gravados,
        ];
    }

    // ---------------------------------------------------------------------
    // Montagem (usada pelo carregar e pelo consolidar)
    // ---------------------------------------------------------------------

    private function logicaDeMontagem($idProcesso) {
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) throw new Exception('ID invalido.');

        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        if (empty($participantes) || empty($itens)) {
            return ['cabecalho' => [], 'linhas' => [], 'total_fmt' => '0,00', 'total_raw' => 0.0];
        }

        // Mapa: [num-seq][fornecedor] => detalhes
        $mapa = [];
        foreach ($ofertasBrutas as $o) {
            $chaveItem = $o['num_solicitacao'] . '-' . $o['seq_solicitacao'];
            $fid = (int)$o['id_fornecedor_senior'];

            $mapa[$chaveItem][$fid] = [
                'oferta_id' => (int)$o['id'],
                'valor' => (float)($o['valor_unitario'] ?? 0),
                'atende' => ((int)($o['atende'] ?? 1)) === 1,
                'justificativa_da_recusa' => $o['justificativa_da_recusa'] ?? '',
            ];
        }

        // Cabeçalho
        $cabecalho = [];
        foreach ($participantes as $p) {
            $nomes = explode(' ', trim((string)($p['nome'] ?? '')));
            $nomeCurto = $nomes[0] . (isset($nomes[1]) ? ' ' . $nomes[1] : '');
            $cabecalho[] = [
                'id' => (int)$p['id'],
                'nome_completo' => $this->utf8((string)$p['nome']),
                'nome_curto' => $this->utf8($nomeCurto),
            ];
        }

        $linhas = [];
        $totalGeral = 0.0;

        foreach ($itens as $item) {
            $num = (int)$item['num_solicitacao'];
            $seq = (int)$item['seq_solicitacao'];
            $idItem = isset($item['id_item']) ? (int)$item['id_item'] : 0;

            $chave = $num . '-' . $seq;
            $qtd = (float)($item['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;

            $descSenior = $this->repo->buscarDescricaoSenior($num, $seq);
            $nomeProduto = $descSenior ? $this->utf8($descSenior) : "Produto ($chave)";

            $ofertasItem = $mapa[$chave] ?? [];

            // menor apenas entre ofertas que atendem
            $validos = [];
            foreach ($ofertasItem as $d) {
                $v = (float)($d['valor'] ?? 0);
                if ($v > 0 && !empty($d['atende'])) $validos[] = $v;
            }

            $menor = !empty($validos) ? min($validos) : null;
            if ($menor !== null) {
                $totalGeral += ($menor * $qtd);
            }

            $empates = 0;
            if ($menor !== null) {
                foreach ($validos as $v) {
                    if (abs($v - $menor) < 0.001) $empates++;
                }
            }

            $celulas = [];
            foreach ($participantes as $p) {
                $fid = (int)$p['id'];
                $d = $ofertasItem[$fid] ?? null;

                $valor = $d ? (float)($d['valor'] ?? 0) : 0.0;
                $atende = $d ? (bool)($d['atende'] ?? true) : true;
                $ofertaId = $d ? (int)($d['oferta_id'] ?? 0) : 0;
                $just = $d ? (string)($d['justificativa_da_recusa'] ?? '') : '';

                $status = 'empty';
                if ($valor > 0) {
                    if (!$atende) {
                        $status = 'rejected';
                    } elseif ($menor !== null && abs($valor - $menor) < 0.001) {
                        $status = ($empates > 1) ? 'tie' : 'winner';
                    } else {
                        $status = 'loser';
                    }
                }

                $celulas[$fid] = [
                    'oferta_id' => $ofertaId,
                    'valor' => $valor,
                    'valor_fmt' => $valor > 0 ? number_format($valor, 2, ',', '.') : '-',
                    'status' => $status,
                    'atende' => $atende,
                    'justificativa_da_recusa' => $just,
                ];
            }

            $linhas[] = [
                'id_item' => $idItem,
                'chave' => $chave,
                'produto' => $nomeProduto,
                'qtd_raw' => $qtd,
                'qtd_fmt' => number_format($qtd, 2, ',', '.'),
                'melhor_raw' => $menor,
                'melhor_fmt' => ($menor !== null) ? number_format($menor, 2, ',', '.') : '-',
                'celulas' => $celulas,
            ];
        }

        return [
            'cabecalho' => $cabecalho,
            'linhas' => $linhas,
            'total_fmt' => number_format($totalGeral, 2, ',', '.'),
            'total_raw' => $totalGeral,
        ];
    }

    private function utf8($str) {
        $str = (string)$str;
        if ($str === '') return '';
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str);
        }
        return $str;
    }
}
