<?php
class EmailFornecedoresRepo {
    private $pdo;       // MySQL
    private $connSenior; // SQL Server

    public function __construct($pdo, $connSenior) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // -------------------------------------------------------------------------
    // ETAPA 1: SINCRONIZAÇÃO (Senior -> MySQL Local)
    // -------------------------------------------------------------------------

    // Busca participantes vinculados a esta instância de processo
    public function buscarParticipantes($idProcesso) {
        $sql = "SELECT id, id_fornecedor_senior, nome_do_fornecedor 
                FROM licitacao_participantes 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca emails no Senior (Exemplo genérico buscando na tabela de fornecedores e contatos)
    // Ajuste a query do Senior conforme a estrutura real do seu ERP (R034FON, E095CON, etc)
    public function buscarEmailsNoSenior($codFornecedor) {
        if (!$this->connSenior) return [];

        $emails = [];
        
        // 1. Email Principal do Fornecedor (E095FOR)
        $sql1 = "SELECT intnet as email FROM Sapiens.sapiens.e095for WHERE codfor = ?";
        $stmt1 = $this->connSenior->prepare($sql1);
        $stmt1->execute([$codFornecedor]);
        if ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['email'])) $emails[] = strtolower(trim($row['email']));
        }

        // 2. Emails de Contatos (E095CON - Exemplo)
        // Adicione aqui se quiser buscar contatos também
        /*
        $sql2 = "SELECT intnet as email FROM Sapiens.sapiens.e095con WHERE codfor = ?";
        $stmt2 = $this->connSenior->prepare($sql2);
        $stmt2->execute([$codFornecedor]);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
             if (!empty($row['email'])) $emails[] = strtolower(trim($row['email']));
        }
        */

        return array_unique($emails); // Remove duplicados
    }

    // Insere ou atualiza na tabela mestre (email_fornecedor)
    public function upsertEmailMestre($codFornecedor, $email) {
        $sql = "INSERT INTO email_fornecedor (id_fornecedor_senior, email_fornecedor) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE data_atualizacao = NOW()"; // Apenas atualiza data se já existir
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codFornecedor, $email]);
    }

    // -------------------------------------------------------------------------
    // ETAPA 2: LEITURA PARA O FRONT (MySQL -> Vue)
    // -------------------------------------------------------------------------

    public function buscarEmailsParaSelecao($idProcesso) {
        // Traz todos os emails conhecidos dos fornecedores deste processo
        // E faz um LEFT JOIN com email_instancia para saber se já está marcado (checked)
        $sql = "SELECT 
                    lp.id as id_participante,
                    lp.nome_do_fornecedor,
                    lp.id_fornecedor_senior,
                    ef.email_fornecedor,
                    (CASE WHEN ei.id IS NOT NULL THEN 1 ELSE 0 END) as selecionado
                FROM licitacao_participantes lp
                INNER JOIN email_fornecedor ef ON lp.id_fornecedor_senior = ef.id_fornecedor_senior
                LEFT JOIN email_instancia ei 
                    ON ei.id_licitacao_participante = lp.id 
                    AND ei.email_fornecedor = ef.email_fornecedor
                    AND ei.id_processo_instancia = ?
                WHERE lp.id_processo_instancia = ?
                ORDER BY lp.nome_do_fornecedor, ef.email_fornecedor";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // ETAPA 3: SALVAR SELEÇÃO
    // -------------------------------------------------------------------------

    public function limparSelecaoAnterior($idProcesso) {
        // Remove todos os registros desta instância para regravar apenas os selecionados
        // Isso é mais seguro para lidar com desmarcações
        $sql = "DELETE FROM email_instancia WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
    }

    public function salvarEmailInstancia($idProcesso, $idParticipante, $idFornSenior, $email) {
        $sql = "INSERT INTO email_instancia 
                (id_processo_instancia, id_licitacao_participante, id_fornecedor_senior, email_fornecedor, flag_ativo)
                VALUES (?, ?, ?, ?, 1)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $idParticipante, $idFornSenior, $email]);
    }
}
?>