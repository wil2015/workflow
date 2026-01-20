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

        $idsVinculados = $instance_id ? $this->repo->getIdsVinculados($instance_id) : [];

        $condicao = "WHERE sitfor = 'A'";
        $sqlParams = [];
        if (!empty($search)) {
            $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
            $sqlParams = ["%$search%", "%$search%", "%$search%"];
        }

        $orderBy = !empty($idsVinculados) 
            ? "CASE WHEN codfor IN (" . implode(',', array_map('intval', $idsVinculados)) . ") THEN 1 ELSE 0 END DESC, nomfor ASC"
            : "nomfor ASC";

        $totalRecords = $this->repo->buscarTotalSenior("WHERE sitfor = 'A'", []);
        $totalFiltered = !empty($search) ? $this->repo->buscarTotalSenior($condicao, $sqlParams) : $totalRecords;
        
        $rawSenior = $this->repo->buscarFornecedoresSenior($condicao, $orderBy, $start, $length, $sqlParams);

        $data = [];
        $mapaVinculados = array_fill_keys($idsVinculados, true);

        foreach ($rawSenior as $row) {
            $cod = (int)$row['codfor'];
            
            // USO DA HERANÇA AQUI
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

        return ["draw" => (int)($params['draw'] ?? 1), "recordsTotal" => $totalRecords, "recordsFiltered" => $totalFiltered, "data" => $data];
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
        // Reutiliza o método utf8 do pai
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->utf8($string));
        $limpo = preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii);
        return strtoupper(trim(preg_replace('/\s+/', ' ', $limpo)));
    }
}