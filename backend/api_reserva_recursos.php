<?php
// backend/api_reserva_recursos.php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$idProcesso = $_GET['instance_id'] ?? null;
if (!$idProcesso) { echo json_encode(['erro' => 'ID inválido']); exit; }

// Busca valor, id senior e dados já salvos
$stmt = $pdo->prepare("SELECT 
                        valor_final_processo, 
                        id_processo_senior, 
                        data_inicio,
                        reserva_dotacao, 
                        reserva_observacao 
                       FROM processos_instancia 
                       WHERE id = ?");
$stmt->execute([$idProcesso]);
$proc = $stmt->fetch(PDO::FETCH_ASSOC);

$valor = (float)($proc['valor_final_processo'] ?? 0);
$ano = date('Y', strtotime($proc['data_inicio']));

echo json_encode([
    'sucesso' => true,
    'processo_numero' => $proc['id_processo_senior'] . '/' . $ano,
    'total_valor' => $valor,
    'total_formatado' => number_format($valor, 2, ',', '.'),
    'dados_salvos' => [
        'dotacao' => $proc['reserva_dotacao'],
        'observacao' => $proc['reserva_observacao']
    ]
]);
?>