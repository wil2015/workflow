<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/GradeComparativaRepo.php';

class GradeComparativaService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    public function montarGradeParaFront($idProcesso) {
        return $this->logicaDeMontagem((int)$idProcesso);
    }

    public function consolidarProcesso($idProcesso, $ofertas = [])
    {
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) throw new Exception('ID do processo invalido.');

        // 1) Persiste atende/justificativa
        foreach (($ofertas ?: []) as $o) {
            $ofertaId = (int)($o['oferta_id'] ?? 0);
            if ($ofertaId <= 0) continue;
            
            $atende = !empty($o['atende']);
            $just = $atende ? null : mb_substr(trim((string)($o['justificativa_da_recusa'] ?? '')), 0, 50);
            
            if (!$atende && empty($just)) throw new Exception('Justificativa obrigatória para recusa.');

            $this->repo->atualizarAtendeJustificativa($ofertaId, $atende, $just);
        }

        // 2) Recalcula
        $dados = $this->logicaDeMontagem($idProcesso);
        $this->repo->salvarValorFinal($idProcesso, (float)($dados['total_raw'] ?? 0));
        $this->repo->limparGradeDeCustos($idProcesso);

        // 3) Grava vencedores
        $rows = [];
        foreach (($dados['linhas'] ?? []) as $linha) {
            foreach (($linha['celulas'] ?? []) as $fid => $c) {
                if (($c['status'] === 'winner' || $c['status'] === 'tie') && !empty($c['atende'])) {
                    $rows[] = [
                        'id_fornecedor_senior' => (int)$fid,
                        'id_item' => (int)$linha['id_item'],
                        'valor_cotado' => (float)$c['valor'],
                    ];
                }
            }
        }
        $gravados = $this->repo->inserirGradeDeCustos($idProcesso, $rows);

        return ['sucesso' => true, 'valor_gravado' => $dados['total_fmt'] ?? '0,00', 'itens_gravados' => $gravados];
    }

    private function logicaDeMontagem($idProcesso)
    {
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        // Mapa de Ofertas
        $mapa = [];
        foreach ($ofertasBrutas as $o) {
            $mapa[$o['num_solicitacao'] . '-' . $o['seq_solicitacao']][(int)$o['id_fornecedor_senior']] = [
                'oferta_id' => (int)$o['id'],
                'valor' => (float)($o['valor_unitario'] ?? 0),
                'atende' => ((int)($o['atende'] ?? 1)) === 1,
                'justificativa' => $o['justificativa_da_recusa'] ?? '',
            ];
        }

        // Cabeçalho
        $cabecalho = array_map(function($p) {
            $nomes = explode(' ', trim($p['nome']));
            return [
                'id' => (int)$p['id'],
                'nome_completo' => $this->utf8($p['nome']), // Herança
                'nome_curto' => $this->utf8($nomes[0] . (isset($nomes[1]) ? ' ' . $nomes[1] : ''))
            ];
        }, $participantes);

        $linhas = [];
        $totalGeral = 0.0;

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            $qtd = max((float)($item['quantidade'] ?? 1), 1);
            
            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->utf8($descSenior) : "Produto ($chave)";

            $ofertasItem = $mapa[$chave] ?? [];
            
            // Cálculo do Menor Preço (apenas os que atendem)
            $validos = [];
            foreach ($ofertasItem as $d) {
                if (($d['valor'] > 0) && $d['atende']) $validos[] = $d['valor'];
            }
            $menor = !empty($validos) ? min($validos) : null;
            if ($menor !== null) $totalGeral += ($menor * $qtd);

            // Monta células
            $celulas = [];
            foreach ($participantes as $p) {
                $d = $ofertasItem[$p['id']] ?? null;
                $val = $d ? $d['valor'] : 0.0;
                $atende = $d ? $d['atende'] : true;
                
                $status = 'empty';
                if ($val > 0) {
                    if (!$atende) $status = 'rejected';
                    elseif ($menor !== null && abs($val - $menor) < 0.001) $status = (count(array_keys($validos, $menor)) > 1) ? 'tie' : 'winner';
                    else $status = 'loser';
                }

                $celulas[$p['id']] = [
                    'oferta_id' => $d['oferta_id'] ?? 0,
                    'valor' => $val,
                    'valor_fmt' => $val > 0 ? number_format($val, 2, ',', '.') : '-',
                    'status' => $status,
                    'atende' => $atende,
                    'justificativa_da_recusa' => $d['justificativa'] ?? '',
                ];
            }

            $linhas[] = [
                'id_item' => (int)$item['id_item'],
                'chave' => $chave,
                'produto' => $nomeProduto,
                'qtd_fmt' => number_format($qtd, 2, ',', '.'),
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
}