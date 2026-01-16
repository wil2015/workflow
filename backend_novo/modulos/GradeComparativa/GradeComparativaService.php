<?php
require_once 'GradeComparativaRepo.php';

class GradeComparativaService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    public function montarGradeParaFront($idProcesso) {
        return $this->logicaDeMontagem($idProcesso); 
    }

    public function atualizarOferta($dados) {
        $idOferta = $dados['id_oferta'] ?? 0;
        $atende = (isset($dados['atende']) && $dados['atende'] == true) ? 1 : 0;
        $justificativa = $dados['justificativa'] ?? '';

        if (!$idOferta) throw new Exception("ID da oferta não informado.");

        $this->repo->atualizarStatusOferta($idOferta, $atende, $justificativa);
        return ['sucesso' => true];
    }

    private function logicaDeMontagem($idProcesso) {
        if (!$idProcesso) throw new Exception("ID inválido.");

        $participantes = $this->repo->buscarParticipantes($idProcesso);
        // Agora o repo retorna 'id' corretamente
        $itens = $this->repo->buscarItensBasicos($idProcesso); 
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        if (empty($participantes)) return ['cabecalho' => [], 'linhas' => [], 'total_fmt' => '0,00', 'total_raw' => 0];

        // Mapeia ofertas
        $mapaOfertas = [];
        foreach ($ofertasBrutas as $o) {
            $chaveItem = $o['num_solicitacao'] . '-' . $o['seq_solicitacao'];
            $mapaOfertas[$chaveItem][$o['id_fornecedor_senior']] = [
                'id_oferta' => $o['id'],
                'valor' => (float)$o['valor_unitario'],
                'atende' => (int)$o['atende'],
                'justificativa' => $o['justificativa_da_recusa']
            ];
        }

        $cabecalho = [];
        foreach ($participantes as $p) {
            $nomes = explode(' ', trim($p['nome']));
            $nomeCurto = $nomes[0] . (isset($nomes[1]) ? ' ' . $nomes[1] : '');
            $cabecalho[] = [
                'id' => $p['id'],
                'nome_completo' => $this->utf8($p['nome']),
                'nome_curto' => $this->utf8($nomeCurto)
            ];
        }

        $linhas = [];
        $totalGeral = 0.0;
        $vencedoresParaSalvar = []; 

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            
            // [CORREÇÃO]: Pegamos o 'id' vindo da tabela processos_itens
            $idItemBanco = $item['id']; 

            $qtd = (float)($item['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;

            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->utf8($descSenior) : "Produto ($chave)";

            $dadosItem = $mapaOfertas[$chave] ?? [];

            // Filtra preços válidos (quem atende e valor > 0)
            $precosValidos = [];
            foreach ($dadosItem as $fid => $d) {
                if ($d['valor'] > 0 && $d['atende'] === 1) {
                    $precosValidos[] = $d['valor'];
                }
            }

            $menorPreco = !empty($precosValidos) ? min($precosValidos) : null;

            if ($menorPreco) {
                $totalGeral += ($menorPreco * $qtd);
            }

            $celulas = [];
            foreach ($participantes as $p) {
                $fid = $p['id'];
                $info = $dadosItem[$fid] ?? null;
                
                $valor = $info ? $info['valor'] : 0.0;
                $atende = $info ? $info['atende'] : 1; 
                $justificativa = $info ? $this->utf8($info['justificativa']) : '';
                $idOferta = $info ? $info['id_oferta'] : null;

                $status = 'empty';
                if ($valor > 0) {
                    if ($atende === 0) {
                        $status = 'rejected';
                    } elseif ($menorPreco && abs($valor - $menorPreco) < 0.001) {
                        $qtdEmpates = count(array_keys($precosValidos, $menorPreco));
                        $status = ($qtdEmpates > 1) ? 'tie' : 'winner';
                        
                        if ($status === 'winner') {
                            // Salva na lista de vencedores para gravar depois
                            $vencedoresParaSalvar[] = [
                                'id_fornecedor' => $fid,
                                'id_item' => $idItemBanco, // Usa o ID correto da tabela processos_itens
                                'valor' => $valor
                            ];
                        }
                    } else {
                        $status = 'loser';
                    }
                }

                $celulas[$fid] = [
                    'id_oferta' => $idOferta,
                    'valor' => $valor,
                    'valor_fmt' => $valor > 0 ? number_format($valor, 2, ',', '.') : '-',
                    'status' => $status,
                    'atende' => (bool)$atende,
                    'justificativa' => $justificativa
                ];
            }

            $linhas[] = [
                'chave' => $chave,
                'produto' => $nomeProduto,
                'qtd_fmt' => number_format($qtd, 2, ',', '.'),
                'melhor_fmt' => $menorPreco ? number_format($menorPreco, 2, ',', '.') : '-',
                'celulas' => $celulas
            ];
        }

        return [
            'cabecalho' => $cabecalho,
            'linhas' => $linhas,
            'total_fmt' => number_format($totalGeral, 2, ',', '.'),
            'total_raw' => $totalGeral,
            'vencedores_compilados' => $vencedoresParaSalvar
        ];
    }

    public function consolidarProcesso($idProcesso) {
        $dados = $this->logicaDeMontagem($idProcesso);
        $total = $dados['total_raw'];
        $vencedores = $dados['vencedores_compilados'];

        $this->repo->limparGradeCustos($idProcesso);

        foreach ($vencedores as $v) {
            // Insere usando o ID real do item
            $this->repo->inserirItemGrade(
                $idProcesso, 
                $v['id_fornecedor'], 
                $v['id_item'], 
                $v['valor']
            );
        }

        $this->repo->salvarValorFinal($idProcesso, $total);

        return [
            'sucesso' => true, 
            'valor_gravado' => $dados['total_fmt'],
            'qtd_itens_grade' => count($vencedores)
        ];
    }

    private function utf8($str) {
        if (!$str) return '';
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str);
        }
        return $str;
    }
}
?>