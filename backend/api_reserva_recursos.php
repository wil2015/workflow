<?php
// backend/api_reserva_recursos.php
// AGORA MUITO MAIS SIMPLES E SEGURO
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$idProcesso = $_GET['instance_id'] ?? null;

if (!$idProcesso) { echo json_encode(['erro' => 'ID inválido']); exit; }

// Apenas lê o valor que foi gravado na etapa anterior
$stmt = $pdo->prepare("SELECT valor_final_processo FROM processos_instancia WHERE id = ?");
$stmt->execute([$idProcesso]);
$proc = $stmt->fetch(PDO::FETCH_ASSOC);

$valor = (float)($proc['valor_final_processo'] ?? 0);

echo json_encode([
    'sucesso' => true,
    'total_valor' => $valor,
    'total_formatado' => number_format($valor, 2, ',', '.'),
    'msg_status' => ($valor > 0) ? 'Valor recuperado da consolidação.' : 'Aviso: Valor zerado. Verifique se a Grade foi salva.'
]);
?>