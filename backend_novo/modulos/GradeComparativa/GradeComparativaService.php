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

    // Helper seguro para evitar erro de "Method not found"
    private function safeUtf8($str) {
        if (method_exists($this, 'utf8')) {
            return $this->utf8($str);
        }
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1'); // Fallback padrão
    }

    public function montarGradeParaFront($idProcesso) {
        return $this->logicaDeMontagem((int)$idProcesso);
    }

    public function consolidarProcesso($idProcesso, $ofertas = [])
    {
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) throw new Exception('ID do processo invalido.');

        // 1. Atualiza justificativas
        foreach (($ofertas ?: []) as $o) {
            $ofertaId = (int)($o['oferta_id'] ?? 0);
            if ($ofertaId <= 0) continue;
            
            $atende = !empty($o['atende']);
            $just = $atende ? null : mb_substr(trim((string)($o['justificativa_da_recusa'] ?? '')), 0, 50);
            
            $this->repo->atualizarAtendeJustificativa($ofertaId, $atende, $just);
        }

        // 2. Recalcula
        $dados = $this->logicaDeMontagem($idProcesso);

        // 3. Prepara gravação
        $rowsParaGravar = [];
        $linhas = isset($dados['linhas']) ? $dados['linhas'] : [];

        foreach ($linhas as $linha) {
            // Recupera quantidade (Corrigido Zeros)
            $qtdItem = isset($linha['quantidade_raw']) ? (float)$linha['quantidade_raw'] : 0.0;
            if ($qtdItem <= 0.0001) $qtdItem = 1.0; 

            $celulas = isset($linha['celulas']) ? $linha['celulas'] : [];
            
            foreach ($celulas as $fid => $c) {
                if (isset($c['valor']) && (float)$c['valor'] > 0) {
                    $valorUnitario = (float)$c['valor'];
                    $status = isset($c['status']) ? $c['status'] : '';
                    $isVencedor = ($status === 'winner' || $status === 'tie');
                    
                    $rowsParaGravar[] = [
                        'id_fornecedor_senior' => (int)$fid,
                        'id_item' => (int)$linha['id_item'],
                        'quantidade' => $qtdItem,
                        'valor_cotado' => $valorUnitario,
                        'valor_total' => ($valorUnitario * $qtdItem),
                        'vencedor' => $isVencedor ? 1 : 0
                    ];
                }
            }
        }

        // Variável local para closure
        $repo = $this->repo;

        // 4. Transação
        return $this->repo->executarEmTransacao(function() use ($idProcesso, $dados, $rowsParaGravar, $repo) {
            $totalFinal = (float)($dados['total_raw'] ?? 0);
            $repo->limparEAtualizarTotal($idProcesso, $totalFinal);
            $gravados = $repo->inserirLote($idProcesso, $rowsParaGravar);

            return [
                'sucesso' => true, 
                'valor_gravado' => isset($dados['total_fmt']) ? $dados['total_fmt'] : '0,00', 
                'itens_gravados' => $gravados
            ];
        });
    }

    private function logicaDeMontagem($idProcesso)
    {
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);

        // Mapa
        $mapa = [];
        foreach ($ofertasBrutas as $o) {
            $key = $o['num_solicitacao'] . '-' . $o['seq_solicitacao'];
            $fid = (int)$o['id_fornecedor_senior'];
            $mapa[$key][$fid] = [
                'oferta_id' => (int)$o['id'],
                'valor' => (float)($o['valor_unitario'] ?? 0),
                'atende' => ((int)($o['atende'] ?? 1)) === 1,
                'justificativa' => $o['justificativa_da_recusa'] ?? '',
            ];
        }

        // Cabeçalho Seguro
        $cabecalho = [];
        foreach ($participantes as $p) {
            $nomeFull = isset($p['nome']) ? trim($p['nome']) : 'Fornecedor';
            $partes = explode(' ', $nomeFull);
            $nomeCurto = $partes[0];
            if (isset($partes[1])) $nomeCurto .= ' ' . $partes[1];

            $cabecalho[] = [
                'id' => (int)$p['id'],
                'nome_completo' => $this->safeUtf8($nomeFull), 
                'nome_curto' => $this->safeUtf8($nomeCurto)
            ];
        }

        $linhas = [];
        $totalGeral = 0.0;

        // Se $itens for false (erro no SQL), trata como array vazio
        if (!is_array($itens)) $itens = [];

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            
            // Quantidade
            $qtdRaw = isset($item['quantidade']) ? (float)$item['quantidade'] : 0.0;
            $qtd = ($qtdRaw > 0) ? $qtdRaw : 1.0;
            
            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->safeUtf8($descSenior) : "Produto ($chave)";

            $ofertasItem = isset($mapa[$chave]) ? $mapa[$chave] : [];
            
            // Menor Preço
            $validos = [];
            foreach ($ofertasItem as $d) {
                if (($d['valor'] > 0) && $d['atende']) $validos[] = $d['valor'];
            }
            
            $menor = null;
            if (!empty($validos)) {
                $menor = min($validos);
                $totalGeral += ($menor * $qtd);
            }

            // Células
            $celulas = [];
            foreach ($participantes as $p) {
                $pid = (int)$p['id'];
                $d = isset($ofertasItem[$pid]) ? $ofertasItem[$pid] : null;
                
                $val = $d ? $d['valor'] : 0.0;
                $atende = $d ? $d['atende'] : true;
                $just = $d ? $d['justificativa'] : '';
                
                $status = 'empty';
                if ($val > 0) {
                    if (!$atende) {
                        $status = 'rejected';
                    } elseif ($menor !== null && abs($val - $menor) < 0.001) {
                        $countEmpate = 0;
                        foreach($validos as $v) { if(abs($v - $menor) < 0.001) $countEmpate++; }
                        $status = ($countEmpate > 1) ? 'tie' : 'winner';
                    } else {
                        $status = 'loser';
                    }
                }

                $celulas[$pid] = [
                    'oferta_id' => $d ? $d['oferta_id'] : 0,
                    'valor' => $val,
                    'valor_fmt' => $val > 0 ? number_format($val, 2, ',', '.') : '-',
                    'status' => $status,
                    'atende' => $atende,
                    'justificativa_da_recusa' => $just,
                ];
            }

            $linhas[] = [
                'id_item' => (int)$item['id_item'],
                'chave' => $chave,
                'produto' => $nomeProduto,
                'quantidade_raw' => $qtd, // Garante que a quantidade vá para o frontend/consolidação
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