<?php

declare(strict_types=1);

namespace Fornecedores\Service;

use Exception;
use Fornecedores\Repository\FornecedoresRepo;
use Shared\Utils\Formatador;

class FornecedoresService
{
    private FornecedoresRepo $repo;

    public function __construct(FornecedoresRepo $repo)
    {
        $this->repo = $repo;
    }

    public function listarParaDatatable(array $params): array
    {
        $instance_id = (int)($params['instance_id'] ?? 0);
        $idsVinculados = $this->repo->getIdsVinculados($instance_id);

        $resultado = $this->repo->buscarFornecedoresComFiltros(
            (int)($params['start'] ?? 0),
            (int)($params['length'] ?? 10),
            $params['search']['value'] ?? '',
            $idsVinculados
        );

        $data = [];
        $mapaVinculados = array_fill_keys($idsVinculados, true);

        foreach ($resultado['dados'] as $row) {
            $cod = (int)$row['codfor'];
            $nome = Formatador::utf8($row['nomfor']);
            $doc = Formatador::documento($row['cgccpf']);

            $data[] = [
                'cod' => $cod,
                'nome' => $nome,
                'doc' => $doc,
                'cidade_uf' => Formatador::utf8($row['cidfor']) . ' - ' . $row['sigufs'],
                'vinculado' => isset($mapaVinculados[$cod]),
                'json_full' => json_encode(['cod' => $cod, 'nome' => $nome, 'doc' => $doc]),
            ];
        }

        return [
            'draw' => (int)($params['draw'] ?? 1),
            'recordsTotal' => $resultado['total'],
            'recordsFiltered' => $resultado['total'],
            'data' => $data,
        ];
    }

    public function salvarLote(array $dados): array
    {
        $idProcesso = $dados['id_processo'] ?? '';
        if (empty($idProcesso)) throw new Exception('ID do processo e obrigatorio.');

        $count = 0;
        foreach (($dados['participantes'] ?? []) as $jsonItem) {
            $forn = json_decode($jsonItem, true);
            if (!$forn) continue;

            $nomeLimpo = Formatador::limparTexto($forn['nome'] ?? '');
            if (empty($nomeLimpo)) $nomeLimpo = 'FORN ' . $forn['cod'];

            $this->repo->adicionarParticipante($idProcesso, $forn['cod'], $nomeLimpo, $forn['doc']);
            $this->repo->gerarMatrizCotas($idProcesso, $forn['cod']);
            $count++;
        }
        return ['sucesso' => true, 'msg' => "$count fornecedores vinculados!"];
    }

    public function remover($idProcesso, $codFornecedor): array
    {
        $this->repo->removerParticipante($idProcesso, $codFornecedor);
        return ['sucesso' => true, 'msg' => 'Removido.'];
    }
}
