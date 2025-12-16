<?php
// backend/acoes/gerenciar_reserva.php
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'salvar_reserva') {
        $id = $_POST['id_processo'] ?? null;
        $dotacao = $_POST['dotacao'] ?? '';
        $obs = $_POST['observacao'] ?? '';

        if (!$id) throw new Exception("ID inválido.");

        $sql = "UPDATE processos_instancia 
                SET reserva_dotacao = ?, 
                    reserva_observacao = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dotacao, $obs, $id]);

        echo json_encode(['sucesso' => true]);
    } else {
        throw new Exception("Ação desconhecida.");
    }
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>