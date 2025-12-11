<?php
// backend/acoes/enviar_autorizacao.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'registrar_envio') {
        $idProcesso = $_POST['id_processo'];
        $idForn = $_POST['id_fornecedor'];

        if (!$idProcesso || !$idForn) throw new Exception("Dados incompletos.");

        // 1. Grava no Histórico
        $stmt = $pdo->prepare("INSERT INTO historico_autorizacoes (id_processo_instancia, id_fornecedor_senior, data_envio) VALUES (?, ?, NOW())");
        $stmt->execute([$idProcesso, $idForn]);

        // 2. Simulação de envio de E-mail/PDF
        // Aqui entraria a rotina de gerar o PDF com a lista de itens ganhos
        
        ob_clean();
        echo json_encode([
            'sucesso' => true, 
            'msg' => "Autorização registrada e enviada com sucesso!"
        ]);
    } else {
        throw new Exception("Ação desconhecida.");
    }
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>