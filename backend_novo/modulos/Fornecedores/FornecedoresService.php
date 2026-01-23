<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/FornecedoresRepo.php';

class FornecedoresService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new FornecedoresRepo($pdo, $connSenior);
    }

    public function listarParaDatatable($params)
    {
        $instance_id = (int)($params['instance_id'] ?? 0);
        $start       = (int)($params['start'] ?? 0);
        $length      = (int)($params['length'] ?? 10);
        $search      = $params['search']['value'] ?? '';

        // 1. Busca IDs que já estão no processo para marcá-los visualmente e ordenar
        $idsVinculados = $instance_id ? $this->repo->getIdsVinculados($instance_id) : [];

        // 2. Busca no Senior usando o método limpo do Repo (que usa o Query Object)
        $resultado = $this->repo->buscarFornecedoresComFiltros($start, $length, $search, $idsVinculados);

        // 3. Formata para o Front
        $data = [];
        $mapaVinculados = array_fill_keys($idsVinculados, true);

        foreach ($resultado['dados'] as $row) {
            $cod = (int)$row['codfor'];
            $nome   = $this->utf8($row['nomfor']);
            $cidade = $this->utf8($row['cidfor']);
            $uf     = $this->utf8($row['sigufs']);
            
            $data[] = [
                'cod' => $cod,
                'nome' => $nome,
                'doc' => trim($row['cgccpf']),
                'cidade_uf' => $cidade . ' - ' . $uf,
                'vinculado' => isset($mapaVinculados[$cod]),
                'json_full' => json_encode(['cod' => $cod, 'nome' => $nome, 'doc' => trim($row['cgccpf'])])
            ];
        }

        return [
            "draw" => (int)($params['draw'] ?? 1), 
            "recordsTotal" => $resultado['total'], 
            "recordsFiltered" => $resultado['total'], 
            "data" => $data
        ];
    }

    public function salvarLote($dados)
    {
        $idProcesso = $dados['id_processo'] ?? '';
        $participantes = $dados['participantes'] ?? []; 
        if (empty($idProcesso)) throw new Exception("ID do processo é obrigatório.");

        $count = 0;
        foreach ($participantes as $jsonItem) {
            $forn = json_decode($jsonItem, true);
            if (!$forn) continue;

            // Mantivemos a lógica de limpeza de nome aqui, pois é regra de negócio
            $nomeLimpo = $this->limparNomeNuclear($forn['nome'] ?? '');
            if (empty($nomeLimpo)) $nomeLimpo = "FORN " . $forn['cod'];

            $this->repo->adicionarParticipante($idProcesso, $forn['cod'], $nomeLimpo, $forn['doc']);
            $this->repo->gerarMatrizCotas($idProcesso, $forn['cod']);
            $count++;
        }
        return ['sucesso' => true, 'msg' => "$count fornecedores vinculados com sucesso!"];
    }

    public function remover($idProcesso, $codFornecedor)
    {
        $this->repo->removerParticipante($idProcesso, $codFornecedor);
        return ['sucesso' => true, 'msg' => "Fornecedor removido."];
    }

    private function limparNomeNuclear($string)
    {
        if (empty($string)) return "";
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->utf8($string));
        $limpo = preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii);
        return strtoupper(trim(preg_replace('/\s+/', ' ', $limpo)));
    }
}