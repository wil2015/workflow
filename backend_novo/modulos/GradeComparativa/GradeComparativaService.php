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
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        $itens = $this->repo->buscarItensBasicos($idProcesso);
        $ofertasBrutas = $this->repo->buscarTodasOfertas($idProcesso);
        
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

        $cabecalho = [];
        foreach ($participantes as $p) {
            $nomeFull = isset($p['nome']) ? trim($p['nome']) : 'Fornecedor';
            $cabecalho[] = [
                'id' => (int)$p['id'],
                'nome_completo' => Formatador::utf8($nomeFull), 
                'nome_curto' => Formatador::utf8(explode(' ', $nomeFull)[0])
            ];
        }

        $linhas = [];
        $totalGeral = 0.0;
        foreach ($itens as $item) {
            $chave = $item['num_solicitacao'] . '-' . $item['seq_solicitacao'];
            $qtd = (float)$item['quantidade'] > 0 ? (float)$item['quantidade'] : 1.0;
            $descSenior = $this->repo->buscarDescricaoSenior($item['num_solicitacao'], $item['seq_solicitacao']);

            $ofertasItem = $mapa[$chave] ?? [];
            $validos = [];
            foreach ($ofertasItem as $d) {
                if (($d['valor'] > 0) && $d['atende']) $validos[] = $d['valor'];
            }
            
            $menor = !empty($validos) ? min($validos) : null;
            if ($menor) $totalGeral += ($menor * $qtd);

            $celulas = [];
            foreach ($participantes as $p) {
                $pid = (int)$p['id'];
                $d = $ofertasItem[$pid] ?? null;
                $val = $d ? $d['valor'] : 0.0;
                $atende = $d ? $d['atende'] : true;
                
                $status = 'empty';
                if ($val > 0) {
                    if (!$atende) $status = 'rejected';
                    elseif ($menor && abs($val - $menor) < 0.001) $status = 'winner';
                    else $status = 'loser';
                }

                $celulas[$pid] = [
                    'oferta_id' => $d ? $d['oferta_id'] : 0,
                    'valor' => $val,
                    'valor_fmt' => $val > 0 ? Formatador::moeda($val) : '-',
                    'status' => $status,
                    'atende' => $atende,
                    'justificativa_da_recusa' => $d ? $d['justificativa'] : '',
                ];
            }

            $linhas[] = [
                'id_item' => (int)$item['id_item'],
                'chave' => $chave,
                'produto' => $descSenior ? Formatador::utf8($descSenior) : "Item $chave",
                'qtd_fmt' => Formatador::numero($qtd), 
                'melhor_fmt' => $menor ? Formatador::moeda($menor) : '-',
                'celulas' => $celulas,
            ];
        }

        return [
            'cabecalho' => $cabecalho,
            'linhas' => $linhas,
            'total_fmt' => Formatador::moeda($totalGeral),
        ];
    }
}