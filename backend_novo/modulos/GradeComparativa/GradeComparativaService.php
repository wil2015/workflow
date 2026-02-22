<?php
namespace App\Modulos\GradeComparativa;

use App\Core\BaseService;
use App\Core\Utils\Formatador;
use Exception;

class GradeComparativaService extends BaseService
{
    private $repo;

    public function __construct(GradeComparativaRepo $repo) {
        $this->repo = $repo;
    }

    public function montarGradeParaFront($idProcesso) {
        return $this->logicaDeMontagem((int)$idProcesso);
    }

    public function consolidarProcesso($idProcesso, $ofertas = []) {
        // ... (Mantido igual: atualiza ofertas e chama procedure de escrita) ...
        $idProcesso = (int)$idProcesso;
        if ($idProcesso <= 0) throw new Exception('ID invalido.');

        foreach (($ofertas ?: []) as $o) {
             $this->repo->atualizarAtendeJustificativa($o['oferta_id'], !empty($o['atende']), $o['justificativa_da_recusa'] ?? '');
        }

        $dadosFinais = $this->repo->executarEmTransacao(function() use ($idProcesso) {
            $this->repo->executarProcedureConsolidacao($idProcesso);
            return [
                'valor_final' => $this->repo->buscarValorFinalProcesso($idProcesso),
                'itens_gravados' => $this->repo->contarItensGrade($idProcesso)
            ];
        });

        return [
            'sucesso' => true, 
            'valor_gravado' => Formatador::moeda((float)$dadosFinais['valor_final']), 
            'itens_gravados' => $dadosFinais['itens_gravados']
        ];
    }

    private function logicaDeMontagem($idProcesso) {
        // 1. Busca TUDO processado via Procedure
        $dadosRaw = $this->repo->buscarGradeSimulada($idProcesso);
        
        $linhas = [];
        $cabecalho = [];
        $participantesMap = [];
        $totalGeral = 0.0;

        // 2. Agrupa os dados planos em estrutura de Matriz (Pivot)
        foreach ($dadosRaw as $r) {
            // Constrói lista única de participantes para o cabeçalho
            $fid = $r['id_fornecedor'];
            if (!isset($participantesMap[$fid])) {
                $participantesMap[$fid] = [
                    'id' => $fid,
                    'nome_completo' => Formatador::utf8($r['nome_do_fornecedor']),
                    'nome_curto' => Formatador::utf8(explode(' ', $r['nome_do_fornecedor'])[0])
                ];
            }

            // Identificador do Item (Linha da Tabela)
            $chaveItem = $r['num_solicitacao'] . '-' . $r['seq_solicitacao'];
            
            if (!isset($linhas[$chaveItem])) {
                // Se é a primeira vez que vemos o item, buscamos descrição e inicializamos
                $desc = $this->repo->buscarDescricaoSenior($r['num_solicitacao'], $r['seq_solicitacao']);
                $qtd = (float)$r['quantidade'];
                
                $linhas[$chaveItem] = [
                    'id_item' => $r['id_item'],
                    'chave' => $chaveItem,
                    'produto' => $desc ? Formatador::utf8($desc) : "Item $chaveItem",
                    'qtd_fmt' => Formatador::numero($qtd),
                    'melhor_fmt' => ($r['menor_valor'] > 0) ? Formatador::moeda((float)$r['menor_valor']) : '-',
                    'celulas' => [] // Será preenchido abaixo
                ];

                // Somatória do Total Geral (Apenas 1 vez por item, se houver vencedor)
                if ($r['menor_valor'] > 0) {
                    $totalGeral += ($r['menor_valor'] * $qtd);
                }
            }

            // Adiciona a Célula do Fornecedor na Linha
            $linhas[$chaveItem]['celulas'][$fid] = [
                'oferta_id' => $r['oferta_id'],
                'valor' => (float)$r['valor'],
                'valor_fmt' => ($r['valor'] > 0) ? Formatador::moeda((float)$r['valor']) : '-',
                'status' => $r['status_calculado'], // Vem pronto do SQL!
                'atende' => (bool)$r['atende'],
                'justificativa_da_recusa' => Formatador::utf8($r['justificativa_da_recusa'])
            ];
        }

        // Ordena participantes pelo nome (opcional, já vem do SQL mas garante chaves)
        sort($participantesMap);

        return [
            'cabecalho' => array_values($participantesMap),
            'linhas' => array_values($linhas),
            'total_fmt' => Formatador::moeda($totalGeral),
        ];
    }
}