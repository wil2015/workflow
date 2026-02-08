<?php
/*require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/GradeComparativaRepo.php';*/
namespace App\Modulos\GradeComparativa;

use App\Core\BaseService;
use Exception;
class GradeComparativaService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new GradeComparativaRepo($pdo, $connSenior);
    }

    private function safeUtf8($str) {
        if (method_exists($this, 'utf8')) {
            return $this->utf8($str);
        }
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }

    public function montarGradeParaFront($idProcesso) {
        // Mantém a lógica de visualização em tempo real (sem persistência)
        // para o usuário ver o que está acontecendo antes de salvar.
        return $this->logicaDeMontagem((int)$idProcesso);
    }

    public function consolidarProcesso($idProcesso, $ofertas = [])
    {
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) throw new Exception('ID do processo invalido.');

        // 1. Atualiza justificativas e flags "Atende" no banco (licitacao_itens_ofertados)
        // Isso é pré-requisito para a Procedure funcionar corretamente
        foreach (($ofertas ?: []) as $o) {
            $ofertaId = (int)($o['oferta_id'] ?? 0);
            if ($ofertaId <= 0) continue;
            
            $atende = !empty($o['atende']);
            // Se atende, limpa justificativa, senão grava a justificativa truncada
            $just = $atende ? null : mb_substr(trim((string)($o['justificativa_da_recusa'] ?? '')), 0, 50);
            
            $this->repo->atualizarAtendeJustificativa($ofertaId, $atende, $just);
        }

        // 2. Transação simplificada: Executa a Procedure
        // A procedure já contém DELETE, INSERT e UPDATE do valor final
        
        $dadosFinais = $this->repo->executarEmTransacao(function() use ($idProcesso) {
            
            // A) Executa a lógica pesada no banco
            $this->repo->executarProcedureConsolidacao($idProcesso);

            // B) Busca os dados atualizados para retorno
            $valorFinal = $this->repo->buscarValorFinalProcesso($idProcesso);
            $qtdItens = $this->repo->contarItensGrade($idProcesso);

            return [
                'valor_final' => (float)$valorFinal,
                'itens_gravados' => (int)$qtdItens
            ];
        });

        // 3. Retorno formatado para o Vue
        return [
            'sucesso' => true, 
            'valor_gravado' => number_format($dadosFinais['valor_final'], 2, ',', '.'), 
            'itens_gravados' => $dadosFinais['itens_gravados']
        ];
    }

    // Mantido para visualização do Grid no Frontend (GET)
    private function logicaDeMontagem($idProcesso)
    {
        // ... (Mantenha o código original da logicaDeMontagem aqui intacto)
        // Apenas para visualização em tela, não afeta a gravação do banco.
        // O código original fornecido na pergunta para esta função estava correto
        // para fins de exibição (renderização da tabela).
        
        // REPLICANDO O INICIO PARA CONTEXTO (Mantenha o resto da função original):
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);
        
        // ... (Restante da lógica original de logicaDeMontagem) ...
        
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
        if (!is_array($itens)) $itens = [];

        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            $qtdRaw = isset($item['quantidade']) ? (float)$item['quantidade'] : 0.0;
            $qtd = ($qtdRaw > 0) ? $qtdRaw : 1.0;
            
            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);
            $nomeProduto = $descSenior ? $this->safeUtf8($descSenior) : "Produto ($chave)";

            $ofertasItem = isset($mapa[$chave]) ? $mapa[$chave] : [];
            
            $validos = [];
            foreach ($ofertasItem as $d) {
                if (($d['valor'] > 0) && $d['atende']) $validos[] = $d['valor'];
            }
            
            $menor = null;
            if (!empty($validos)) {
                $menor = min($validos);
                $totalGeral += ($menor * $qtd);
            }

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
                'quantidade_raw' => $qtd,
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