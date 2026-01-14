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

        // 1. Busca IDs já vinculados
        $idsVinculados = $instance_id ? $this->repo->getIdsVinculados($instance_id) : [];

        // 2. Filtros SQL
        $condicao = "WHERE sitfor = 'A'";
        $sqlParams = [];

        if (!empty($search)) {
            $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
            $term = "%$search%";
            $sqlParams = [$term, $term, $term];
        }

        // 3. Ordenação
        $orderBy = "";
        if (!empty($idsVinculados)) {
            $listaIds = implode(',', array_map('intval', $idsVinculados));
            $orderBy = "CASE WHEN codfor IN ($listaIds) THEN 1 ELSE 0 END DESC, ";
        }
        $orderBy .= "nomfor ASC";

        // 4. Consultas
        $totalRecords = $this->repo->buscarTotalSenior("WHERE sitfor = 'A'", []);
        
        $totalFiltered = $totalRecords;
        if (!empty($search)) {
            $totalFiltered = $this->repo->buscarTotalSenior($condicao, $sqlParams);
        }

        $rawSenior = $this->repo->buscarFornecedoresSenior($condicao, $orderBy, $start, $length, $sqlParams);

        // 5. Formatação (PADRONIZADA NO SERVICE)
        $data = [];
        $mapaVinculados = array_fill_keys($idsVinculados, true);

        foreach ($rawSenior as $row) {
            $cod = (int)$row['codfor'];
            
            // --- PADRONIZAÇÃO: Usando a função utf8() inteligente ---
            $nome   = $this->utf8($row['nomfor']);
            $cidade = $this->utf8($row['cidfor']);
            $uf     = $this->utf8($row['sigufs']);
            // -------------------------------------------------------
            
            $objSalvar = ['cod' => $cod, 'nome' => $nome, 'doc' => trim($row['cgccpf'])];

            $data[] = [
                'cod' => $cod,
                'nome' => $nome,
                'doc' => trim($row['cgccpf']),
                'cidade_uf' => $cidade . ' - ' . $uf,
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

    // --- FUNÇÃO PADRÃO DE CORREÇÃO DE ENCODING ---
    // (A mesma usada no FluxoService e GradeComparativaService)
    private function utf8($str) {
        // Se a string já for UTF-8 válida, retorna ela mesma para evitar "Ã£"
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str); // Converte ISO-8859-1 para UTF-8
        }
        return $str;
    }

    private function limparNomeNuclear($string) {
        if (empty($string)) return "";
        // Garante UTF-8 antes de limpar acentos
        $utf8 = $this->utf8($string);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);
        $limpo = preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii);
        return strtoupper(trim(preg_replace('/\s+/', ' ', $limpo)));
    }
}
?>