<?php
namespace App\Modulos\ExecucaoFluxo\Query;

use PDO;

class ListarOrdensCompraQuery
{
    private const SERVICE_SEQUENCE_OFFSET = 900000;

    private $pdo;
    private $params = [];
    private $whereClauses = [];
    private $colunaPeso = "0";

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function baseSql(): string
    {
        $serviceOffset = self::SERVICE_SEQUENCE_OFFSET;

        return "
            SELECT
                OCP.CODEMP AS codemp,
                OCP.CODFIL AS codfil,
                OCP.NUMOCP AS numero_oc,
                OCP.DATGER AS data_geracao,
                'PRODUTO' AS tipo_item,
                IPO.SEQIPO AS sequencia_original,
                IPO.SEQIPO AS sequencia_workflow,
                CAST(IPO.CODPRO AS VARCHAR(50)) AS codigo_item,
                PRO.DESPRO AS descricao_item,
                IPO.QTDPED AS quantidade,
                IPO.PREUNI AS preco_unitario,
                (IPO.QTDPED * IPO.PREUNI) AS valor_total_item
            FROM Sapiens.sapiens.E420OCP OCP
            INNER JOIN Sapiens.sapiens.E420IPO IPO ON IPO.NUMOCP = OCP.NUMOCP
            LEFT JOIN Sapiens.sapiens.E075PRO PRO ON PRO.CODPRO = IPO.CODPRO
            WHERE OCP.CODEMP = 1
              AND OCP.CODFIL = 1
              AND OCP.SITOCP IN (1, 2, 9)

            UNION ALL

            SELECT
                OCP.CODEMP AS codemp,
                OCP.CODFIL AS codfil,
                OCP.NUMOCP AS numero_oc,
                OCP.DATGER AS data_geracao,
                'SERVICO' AS tipo_item,
                ISO.SEQISO AS sequencia_original,
                ($serviceOffset + ISO.SEQISO) AS sequencia_workflow,
                CAST(ISO.CODSER AS VARCHAR(50)) AS codigo_item,
                SER.DESSER AS descricao_item,
                ISO.QTDPED AS quantidade,
                ISO.PREUNI AS preco_unitario,
                (ISO.QTDPED * ISO.PREUNI) AS valor_total_item
            FROM Sapiens.sapiens.E420OCP OCP
            INNER JOIN Sapiens.sapiens.E420ISO ISO ON ISO.NUMOCP = OCP.NUMOCP
            LEFT JOIN Sapiens.sapiens.E080SER SER ON SER.CODSER = ISO.CODSER
            WHERE OCP.CODEMP = 1
              AND OCP.CODFIL = 1
              AND OCP.SITOCP IN (1, 2, 9)
             
        ";
    }

    public static function detalheSql(): string
    {
        return "
            SELECT TOP 1
                oc.descricao_item AS cplpro,
                oc.quantidade AS qtdsol,
                oc.tipo_item AS unimed,
                oc.preco_unitario AS presol,
                oc.tipo_item,
                oc.codigo_item,
                oc.sequencia_original
            FROM (" . self::baseSql() . ") oc
            WHERE oc.numero_oc = ?
              AND oc.sequencia_workflow = ?
        ";
    }

    public function configurarRegras(array $meusItens, array $bloqueados): void
    {
        $caseParts = [];
        if (!empty($meusItens)) {
            $caseParts[] = "WHEN (" . implode(" OR ", $meusItens) . ") THEN 2";
        }
        if (!empty($bloqueados)) {
            $caseParts[] = "WHEN (" . implode(" OR ", $bloqueados) . ") THEN 1";
        }

        $this->colunaPeso = empty($caseParts) ? "0" : "CASE " . implode(" ", $caseParts) . " ELSE 0 END";

        $filtroStatus = "1 = 1";
        $todosEmUso = array_merge($meusItens, $bloqueados);
        if (!empty($todosEmUso)) {
            $filtroStatus = "($filtroStatus OR (" . implode(" OR ", $todosEmUso) . "))";
        }
        $this->whereClauses[] = $filtroStatus;
    }

    public function aplicarBusca(?string $search): void
    {
        if ($search) {
            $this->whereClauses[] = "(oc.descricao_item LIKE ? OR CAST(oc.numero_oc AS VARCHAR(20)) LIKE ? OR oc.codigo_item LIKE ? OR oc.tipo_item LIKE ?)";
            $termo = "%$search%";
            $this->params[] = $termo;
            $this->params[] = $termo;
            $this->params[] = $termo;
            $this->params[] = $termo;
        }
    }

    public function executar($start, $length, $campoOrdenacao, $dirSQL): array
    {
        $whereSql = "";
        if (!empty($this->whereClauses)) {
            $whereSql = "WHERE " . implode(" AND ", $this->whereClauses);
        }

        $baseOc = self::baseSql();
        $sql = "SELECT
                    oc.codemp,
                    oc.numero_oc,
                    oc.sequencia_workflow,
                    oc.sequencia_original,
                    oc.tipo_item,
                    oc.codigo_item,
                    oc.descricao_item,
                    oc.quantidade,
                    oc.preco_unitario,
                    oc.valor_total_item,
                    oc.data_geracao,
                    {$this->colunaPeso} AS peso_ordenacao
                FROM ($baseOc) oc
                $whereSql
                ORDER BY $campoOrdenacao $dirSQL
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $stmt = $this->pdo->prepare($sql);

        $i = 1;
        foreach ($this->params as $val) {
            $stmt->bindValue($i++, $val);
        }
        $stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);

        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) AS T FROM ($baseOc) oc $whereSql");

        $i = 1;
        foreach ($this->params as $val) {
            $stmtTotal->bindValue($i++, $val);
        }
        $stmtTotal->execute();

        return ['dados' => $dados, 'total' => $stmtTotal->fetchColumn()];
    }
}
