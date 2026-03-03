<?php

declare(strict_types=1);

namespace Cotacao\Service;

use Cotacao\Dto\CotacaoItemDTO;
use Cotacao\Repository\CotacaoRepo;
use Exception;
use Shared\Utils\Formatador;

class CotacaoService
{
    private CotacaoRepo $repo;

    public function __construct(CotacaoRepo $repo)
    {
        $this->repo = $repo;
    }

    public function listarItensComDetalhes($idProcesso): array
    {
        if (!$idProcesso) throw new Exception('ID obrigatorio.');
        $vinculos = $this->repo->buscarItensDoProcesso($idProcesso);
        $lista = [];

        foreach ($vinculos as $v) {
            $detalhe = $this->repo->buscarDetalheSenior($v['num_solicitacao'], $v['seq_solicitacao']);
            $lista[] = [
                'num' => $v['num_solicitacao'],
                'seq' => $v['seq_solicitacao'],
                'desc' => $detalhe ? Formatador::utf8($detalhe['cplpro']) : 'Item nao encontrado',
                'qtd' => Formatador::numero($detalhe['qtdsol'] ?? 0),
                'unid' => trim($detalhe['unimed'] ?? '--'),
            ];
        }
        return $lista;
    }

    public function buscarCotacoesDoItem(array $params): array
    {
        $raw = $this->repo->buscarFornecedoresEValores($params['id_processo'] ?? 0, $params['num'] ?? 0, $params['seq'] ?? 0);
        return array_map(function ($r) {
            return [
                'cod' => $r['id_fornecedor_senior'],
                'nome' => Formatador::utf8($r['nome_do_fornecedor']),
                'valor' => $r['valor_unitario'] !== null ? (float)$r['valor_unitario'] : '',
            ];
        }, $raw);
    }

    public function salvarLote(array $dados): array
    {
        $idProc = $dados['id_processo'] ?? null;
        $numSol = $dados['num_solicitacao'] ?? null;
        $seqSol = $dados['seq_solicitacao'] ?? null;
        if (!$idProc || !$numSol || !$seqSol) throw new Exception('Dados incompletos.');

        $count = 0;
        foreach (($dados['cotacoes'] ?? []) as $itemRaw) {
            $dto = new CotacaoItemDTO($itemRaw);
            if (!$dto->isValido()) continue;

            $this->repo->salvarValorUnitario($idProc, $numSol, $seqSol, $dto->idFornecedor, $dto->valorUnitario);
            $count++;
        }
        return ['sucesso' => true, 'msg' => 'Salvo com sucesso!'];
    }
}
