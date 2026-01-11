<?php
class FluxoRepo {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Leitura do processo
    public function getInstanciaCompleta($id) {
        $sql = "SELECT p.*, d.arquivo_xml, d.nome_do_fluxo, d.id_fluxo_definicao 
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                WHERE p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarIdPorSolicitacao($numSol) {
        $stmt = $this->pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
        $stmt->execute([$numSol]);
        return $stmt->fetchColumn();
    }
    
    // Lista itens para bloquear na tabela
    public function buscarItensOcupados() {
        $sql = "SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA ---

    public function criarProcesso($numSol, $idFluxo) {
        $sql = "INSERT INTO processos_instancia 
                (id_processo_senior, id_processo_instancia, id_fluxo_definicao, data_inicio, status_atual, etapa_bpmn_atual) 
                VALUES (:numsol, :numsol, :fluxo, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':numsol' => $numSol, ':fluxo' => $idFluxo]);
        return $this->pdo->lastInsertId();
    }

    public function adicionarItem($idProcesso, $numSol, $seqSol, $qtd) {
        $sql = "INSERT IGNORE INTO processos_itens 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, quantidade) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $numSol, $seqSol, $qtd]);
        return $stmt->rowCount() > 0;
    }

    public function inicializarCotacao($idProcesso, $numSol, $seqSol) {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                SELECT ?, ?, ?, id_fornecedor_senior, NULL 
                FROM licitacao_participantes 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $numSol, $seqSol, $idProcesso]);
    }

    public function removerItemCompleto($idProcesso, $numSol, $seqSol) {
        // Remove cotações primeiro
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND num_solicitacao = ? AND seq_solicitacao = ?")->execute([$idProcesso, $numSol, $seqSol]);
        
        // Remove o item depois
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = ? AND num_solicitacao = ? AND seq_solicitacao = ?")->execute([$idProcesso, $numSol, $seqSol]);
    }

    public function excluirProcesso($idProcesso) {
        // Exclusão em cascata (Ordem importa para evitar erro de FK se não tiver ON DELETE CASCADE)
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?")->execute([$idProcesso]);
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = ?")->execute([$idProcesso]);
        $this->pdo->prepare("DELETE FROM processos_instancia WHERE id = ?")->execute([$idProcesso]);
    }
}