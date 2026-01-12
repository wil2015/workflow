<?php
require_once 'GradeComparativaRepo.php';

class GradeComparativaService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    public function montarGradeParaFront($idProcesso) {
        // ... (Mantenha o código de leitura igual ao que você já tem) ...
        // Vou resumir aqui para focar na consolidação, mas mantenha o método montarGradeParaFront inteiro.
        
        // [CÓDIGO DE LEITURA JÁ EXISTENTE...] 
        // Se precisar que eu reenvie o montarGradeParaFront, me avise.
        return $this->logicaDeMontagem($idProcesso); 
    }

    // --- LÓGICA CENTRALIZADA (Usada tanto para exibir quanto para salvar) ---
    private function logicaDeMontagem($idProcesso) {
        if (!$idProcesso) throw new Exception("ID inválido.");

        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        if (empty($participantes)) return ['cabecalho' => [], 'linhas' => [], 'total_fmt' => '0,00', 'total_raw' => 0];

        $mapaOfertas = [];
        foreach ($ofertasBrutas as $o) {
            $chaveItem = $o['num_solicitacao'] . '-' . $o['seq_solicitacao'];
            $mapaOfertas[$chaveItem][$o['id_fornecedor_senior']] = (float)$o['valor_unitario'];
        }

        // Cabeçalho
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

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            $qtd = (float)($item['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;

            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->utf8($descSenior) : "Produto ($chave)";

            $precosDesteItem = $mapaOfertas[$chave] ?? [];
            $validos = array_filter($precosDesteItem, function($v) { return $v > 0; });
            $menorPreco = !empty($validos) ? min($validos) : null;

            if ($menorPreco) {
                $totalGeral += ($menorPreco * $qtd);
            }

            $celulas = [];
            foreach ($participantes as $p) {
                $fid = $p['id'];
                $valor = $precosDesteItem[$fid] ?? 0.0;
                
                $status = 'empty';
                if ($valor > 0) {
                    if ($menorPreco && abs($valor - $menorPreco) < 0.001) {
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
                'qtd' => number_format($qtd, 2, ',', '.'), // Adicionado para o Front
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

    // --- NOVA FUNÇÃO DE CONSOLIDAÇÃO ---
    public function consolidarProcesso($idProcesso) {
        // 1. Recalcula tudo usando a mesma lógica da visualização (Segurança Backend)
        $dados = $this->logicaDeMontagem($idProcesso);
        $total = $dados['total_raw'];

        // 2. Chama o Repo para salvar no banco
        $this->repo->salvarValorFinal($idProcesso, $total);

        // 3. Retorna sucesso e o valor formatado para o alerta do Front
        return [
            'sucesso' => true, 
            'valor_gravado' => $dados['total_fmt']
        ];
    }

    private function utf8($str) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
}