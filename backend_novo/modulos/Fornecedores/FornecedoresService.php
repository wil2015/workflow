<?php
require_once 'FornecedoresRepo.php';

class FornecedoresService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new FornecedoresRepo($pdo, $connSenior);
    }

    public function listarParaDatatable($params) {
        $instance_id = (int)($params['instance_id'] ?? 0);
        $start       = (int)($params['start'] ?? 0);
        $length      = (int)($params['length'] ?? 10);
        $search      = $params['search']['value'] ?? '';
        $draw        = (int)($params['draw'] ?? 1);

        // 1. Busca IDs já vinculados para dar destaque
        $idsVinculados = $instance_id ? $this->repo->getIdsVinculados($instance_id) : [];

        // 2. Monta Filtros SQL Server
        $condicao = "WHERE sitfor = 'A'";
        $sqlParams = [];

        if (!empty($search)) {
            $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
            $term = "%$search%";
            $sqlParams = [$term, $term, $term];
        }

        // 3. Ordenação (Vinculados primeiro)
        $orderBy = "";
        if (!empty($idsVinculados)) {
            $listaIds = implode(',', array_map('intval', $idsVinculados));
            $orderBy = "CASE WHEN codfor IN ($listaIds) THEN 1 ELSE 0 END DESC, ";
        }
        $orderBy .= "nomfor ASC";

        // 4. Executa Consultas via Repo
        $totalRecords = $this->repo->buscarTotalSenior("WHERE sitfor = 'A'", []);
        
        $totalFiltered = $totalRecords;
        if (!empty($search)) {
            $totalFiltered = $this->repo->buscarTotalSenior($condicao, $sqlParams);
        }

        $rawSenior = $this->repo->buscarFornecedoresSenior($condicao, $orderBy, $start, $length, $sqlParams);

        // 5. Formata Saída
        $data = [];
        $mapaVinculados = array_fill_keys($idsVinculados, true);

        foreach ($rawSenior as $row) {
            $cod = (int)$row['codfor'];
            $nome = utf8_encode($row['nomfor']);
            
            // Objeto completo para enviar de volta ao salvar
            $objSalvar = ['cod' => $cod, 'nome' => $nome, 'doc' => trim($row['cgccpf'])];

            $data[] = [
                'cod' => $cod,
                'nome' => $nome,
                'doc' => trim($row['cgccpf']),
                'cidade_uf' => utf8_encode($row['cidfor']) . ' - ' . $row['sigufs'],
                'vinculado' => isset($mapaVinculados[$cod]),
                'json_full' => json_encode($objSalvar)
            ];
        }

        return [
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        ];
    }

    public function salvarLote($dados) {
        $idProcesso = $dados['id_processo'] ?? '';
        $participantes = $dados['participantes'] ?? []; 
        
        if (empty($idProcesso)) throw new Exception("ID do processo é obrigatório.");

        $count = 0;
        foreach ($participantes as $jsonItem) {
            $forn = json_decode($jsonItem, true);
            if (!$forn) continue;

            $nomeLimpo = $this->limparNomeNuclear($forn['nome'] ?? '');
            if (empty($nomeLimpo)) $nomeLimpo = "FORN " . $forn['cod'];

            // Salva Fornecedor e Gera Matriz
            $this->repo->adicionarParticipante($idProcesso, $forn['cod'], $nomeLimpo, $forn['doc']);
            $this->repo->gerarMatrizCotas($idProcesso, $forn['cod']);
            $count++;
        }

        return ['sucesso' => true, 'msg' => "$count fornecedores vinculados com sucesso!"];
    }

    public function remover($idProcesso, $codFornecedor) {
        if (!$idProcesso || !$codFornecedor) throw new Exception("Dados inválidos para remoção.");
        
        $this->repo->removerParticipante($idProcesso, $codFornecedor);
        return ['sucesso' => true, 'msg' => "Fornecedor removido."];
    }

    // Utilitário privado de limpeza
    private function limparNomeNuclear($string) {
        if (empty($string)) return "";
        $utf8 = mb_convert_encoding($string, 'UTF-8', 'auto');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);
        $limpo = preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii);
        return strtoupper(trim(preg_replace('/\s+/', ' ', $limpo)));
    }
}