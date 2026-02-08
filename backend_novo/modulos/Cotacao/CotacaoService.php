<?php
/*require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/CotacaoRepo.php';  */
namespace App\Modulos\Cotacao;

use App\Core\BaseService;
use Exception;
class CotacaoService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new CotacaoRepo($pdo, $connSenior);
    }

    public function listarItensComDetalhes($idProcesso)
    {
        if (!$idProcesso) throw new Exception("ID do processo obrigatório.");

        $vinculos = $this->repo->buscarItensDoProcesso($idProcesso);
        $itensCompletos = [];

        foreach ($vinculos as $v) {
            $detalhe = $this->repo->buscarDetalheSenior($v['num_solicitacao'], $v['seq_solicitacao']);
            
            // USO DA HERANÇA
            $desc = $detalhe ? $this->utf8($detalhe['cplpro']) : "Item não encontrado";
            
            $itensCompletos[] = [
                'num' => $v['num_solicitacao'],
                'seq' => $v['seq_solicitacao'],
                'desc' => $desc,
                'qtd' => number_format((float)($detalhe ? $detalhe['qtdsol'] : 0), 2, ',', '.'),
                'unid' => trim($detalhe ? $detalhe['unimed'] : "--")
            ];
        }
        return $itensCompletos;
    }

    public function buscarCotacoesDoItem($params)
    {
        $raw = $this->repo->buscarFornecedoresEValores($params['id_processo'] ?? 0, $params['num'] ?? 0, $params['seq'] ?? 0);
        $lista = [];
        foreach($raw as $r) {
            $lista[] = [
                'cod' => $r['id_fornecedor_senior'],
                'nome' => $this->utf8($r['nome_do_fornecedor']), // USO DA HERANÇA
                'valor' => $r['valor_unitario'] !== null ? (float)$r['valor_unitario'] : '' 
            ];
        }
        return $lista;
    }

    public function salvarLote($dados)
    {
        $idProc = $dados['id_processo'] ?? null;
        $numSol = $dados['num_solicitacao'] ?? null;
        $seqSol = $dados['seq_solicitacao'] ?? null;
        if (!$idProc || !$numSol || !$seqSol) throw new Exception("Identificação incompleta.");

        $count = 0;
        foreach (($dados['cotacoes'] ?? []) as $c) {
            $codForn = $c['cod'] ?? null;
            if (!$codForn) continue;

            $valorFloat = null;
            if (isset($c['valor']) && $c['valor'] !== '') {
                $v = str_replace('.', '', $c['valor']); 
                $valorFloat = (float)str_replace(',', '.', $v);
            }

            $this->repo->salvarValorUnitario($idProc, $numSol, $seqSol, $codForn, $valorFloat);
            $count++;
        }
        return ['sucesso' => true, 'msg' => "Valores salvos com sucesso!"];
    }
}