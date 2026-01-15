<?php
class EmailFornecedoresRepo {
    private $pdo;       
    private $connSenior;

    public function __construct($pdo, $connSenior) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- ETAPA 1: SINCRONIZAÇÃO (Senior -> MySQL) ---

    public function buscarParticipantes($idProcesso) {
        $sql = "SELECT id, id_fornecedor_senior, nome_do_fornecedor 
                FROM licitacao_participantes 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarEmailsNoSenior($codFornecedor) {
        if (!$this->connSenior) return [];

        $emails = [];
        // Busca na tabela de Fornecedores (E095FOR)
        $sql = "SELECT intnet as email FROM Sapiens.sapiens.e095for WHERE codfor = ?";
        
        try {
            $stmt = $this->connSenior->prepare($sql);
            $stmt->execute([$codFornecedor]);
            
            // Pode haver múltiplos contatos ou apenas um campo
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['email'])) {
                    // Quebra por ponto e vírgula se houver múltiplos no mesmo campo
                    $partes = explode(';', $row['email']);
                    foreach($partes as $p) $emails[] = strtolower(trim($p));
                }
            }
        } catch (Exception $e) {
            // Se falhar a conexão Senior, segue vida (usaremos o banco local)
        }

        return array_unique($emails);
    }

    // Este método é o segredo: ele insere se não existir, mas NÃO APAGA os manuais
    public function upsertEmailMestre($codFornecedor, $email) {
        // IGNORE: Se já existir (cod + email), não faz nada. Se não existir, insere.
        $sql = "INSERT IGNORE INTO email_fornecedor (id_fornecedor_senior, email_fornecedor) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codFornecedor, $email]);
    }

    // --- ETAPA 2: LEITURA UNIFICADA (MySQL -> Front) ---

    public function buscarEmailsParaSelecao($idProcesso) {
        // Essa query traz TUDO que está na tabela email_fornecedor para os participantes atuais.
        // Como os manuais também estão lá, eles virão automaticamente.
        $sql = "SELECT 
                    lp.id as id_participante,
                    lp.nome_do_fornecedor,
                    lp.id_fornecedor_senior,
                    ef.email_fornecedor,
                    -- Verifica se este e-mail já foi selecionado NESTA instância específica
                    (CASE WHEN ei.id IS NOT NULL THEN 1 ELSE 0 END) as selecionado
                FROM licitacao_participantes lp
                -- O INNER JOIN garante que pegamos apenas e-mails dos fornecedores desta licitação
                INNER JOIN email_fornecedor ef ON lp.id_fornecedor_senior = ef.id_fornecedor_senior
                -- O LEFT JOIN verifica a seleção atual (checkbox)
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

    // --- ETAPA 3: GRAVAÇÃO DA SELEÇÃO ---

    public function limparSelecaoAnterior($idProcesso) {
        $stmt = $this->pdo->prepare("DELETE FROM email_instancia WHERE id_processo_instancia = ?");
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