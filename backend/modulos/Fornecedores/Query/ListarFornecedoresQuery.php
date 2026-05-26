<?php
namespace App\Modulos\Fornecedores\Query;

use PDO;
class ListarFornecedoresQuery {
    private $pdo;
    private $params = [];
    private $whereClauses = ["sitfor = 'A'"]; // Padrão: Apenas Ativos
    private $colunaOrdenacaoVinculados = "0";

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function priorizarVinculados(array $idsVinculados) {
        // Monta o CASE WHEN para jogar quem já está vinculado para o topo da lista
        if (!empty($idsVinculados)) {
            // Sanitização básica para INT para evitar injeção no IN (...)
            $idsSafe = implode(',', array_map('intval', $idsVinculados));
            $this->colunaOrdenacaoVinculados = "CASE WHEN codfor IN ($idsSafe) THEN 1 ELSE 0 END";
        } else {
            $this->colunaOrdenacaoVinculados = "0";
        }
    }

    public function aplicarBusca(?string $search) {
        if ($search) {
            $this->whereClauses[] = "(nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
            $termo = "%$search%";
            // 3 parâmetros para os 3 '?' acima
            $this->params[] = $termo;
            $this->params[] = $termo;
            $this->params[] = $termo;
        }
    }

    public function executar($start, $length) {
        // Monta WHERE
        $whereSql = "WHERE " . implode(" AND ", $this->whereClauses);

        // --- QUERY DADOS ---
        // Ordena primeiro pelos vinculados (DESC), depois pelo nome (ASC)
        $sql = "SELECT codfor, nomfor, cgccpf, sigufs, cidfor, 
                {$this->colunaOrdenacaoVinculados} as is_vinculado
                FROM Sapiens.sapiens.e095for 
                $whereSql
                ORDER BY is_vinculado DESC, nomfor ASC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $stmt = $this->pdo->prepare($sql);

        // Bind Search
        $i = 1;
        foreach ($this->params as $val) $stmt->bindValue($i++, $val);
        // Bind Pagination
        $stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);
        
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- QUERY TOTAL (CONTAGEM) ---
        // Usa os mesmos filtros de busca
        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) as T FROM Sapiens.sapiens.e095for $whereSql");
        $i = 1;
        foreach ($this->params as $val) $stmtTotal->bindValue($i++, $val);
        $stmtTotal->execute();
        $total = $stmtTotal->fetchColumn();

        return ['dados' => $dados, 'total' => $total];
    }
}