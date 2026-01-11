<?php
require_once 'GradeComparativaRepo.php';

class GradeComparativaService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    public function montarGradeParaFront($idProcesso) {
        if (!$idProcesso) throw new Exception("ID inválido.");

        // 1. Busca Dados Brutos
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        if (empty($participantes)) return ['cabecalho' => [], 'linhas' => [], 'total_fmt' => '0,00'];

        // 2. Mapeia Ofertas para acesso rápido: $mapaOfertas['num-seq']['codForn'] = valor
        $mapaOfertas = [];
        foreach ($ofertasBrutas as $o) {
            $chaveItem = $o['num_solicitacao'] . '-' . $o['seq_solicitacao'];
            $mapaOfertas[$chaveItem][$o['id_fornecedor_senior']] = (float)$o['valor_unitario'];
        }

        // 3. Monta Cabeçalho (Colunas)
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

        // 4. Monta Linhas e Calcula Vencedores
        $linhas = [];
        $totalGeral = 0.0;

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            $qtd = (float)($item['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;

            // Busca Descrição (Senior ou Placeholder)
            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->utf8($descSenior) : "Produto Local ($chave)";

            // Identifica Preços deste item
            $precosDesteItem = $mapaOfertas[$chave] ?? [];
            
            // Filtra só valores válidos (> 0) para achar o menor
            $validos = array_filter($precosDesteItem, function($v) { return $v > 0; });
            $menorPreco = !empty($validos) ? min($validos) : null;

            // Soma ao total geral (Melhor Preço * Qtd)
            if ($menorPreco) {
                $totalGeral += ($menorPreco * $qtd);
            }

            // Monta as células da linha
            $celulas = [];
            foreach ($participantes as $p) {
                $fid = $p['id'];
                $valor = $precosDesteItem[$fid] ?? 0.0;
                
                $status = 'empty';
                if ($valor > 0) {
                    if ($menorPreco && abs($valor - $menorPreco) < 0.001) {
                        // Verifica se é empate (mais de um com o mesmo menor preço)
                        $qtdEmpates = count(array_keys($validos, $menorPreco));
                        $status = ($qtdEmpates > 1) ? 'tie' : 'winner';
                    } else {
                        $status = 'loser';
                    }
                }

                $celulas[$fid] = [
                    'valor' => $valor,
                    'valor_fmt' => $valor > 0 ? number_format($valor, 2, ',', '.') : '-',
                    'status' => $status
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
            'total_raw' => $totalGeral
        ];
    }

    public function consolidarProcesso($idProcesso) {
        // Recalcula tudo para garantir segurança (não confia no front)
        $dados = $this->montarGradeParaFront($idProcesso);
        $total = $dados['total_raw'];

        $this->repo->salvarValorFinal($idProcesso, $total);

        return [
            'sucesso' => true, 
            'valor_gravado' => $dados['total_fmt']
        ];
    }

    private function utf8($str) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
}