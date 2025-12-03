<?php
// backend/router.php

// 1. Recebe o ID da tarefa vindo do JS
$taskId = $_GET['task_id'] ?? '';
$processKey = $_GET['process_key'] ?? 'compras'; // Padrão 'compras' por enquanto

if (empty($taskId)) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID da tarefa não informado']);
    exit;
}

// 2. Conecta ao Banco (Exemplo PDO)
// $pdo = new PDO('mysql:host=db;dbname=seu_banco', 'user', 'pass');
// $stmt = $pdo->prepare("SELECT caminho_view, titulo_modal FROM workflow_etapas WHERE bpmn_task_id = ? AND processo_chave = ?");
// $stmt->execute([$taskId, $processKey]);
// $config = $stmt->fetch(PDO::FETCH_ASSOC);

// --- SIMULAÇÃO (Enquanto você não cria a tabela real) ---
$bancoDeDadosSimulado = [
    'Activity_SelecionarSolicitacao' => [
        'url' => '/backend/views/selecionar_solicitacoes.php',
        'titulo' => 'Seleção de Solicitações'
    ],
    'Activity_SelecionarFornecedores' => [
        'url' => '/backend/views/selecionar_fornecedores.php',
        'titulo' => 'Seleção de Fornecedores'
    ],
    'Activity_ClassificarValores' => [
        'url' => '/backend/views/lancar_valores.php',
        'titulo' => 'Classificação de Propostas'
    ],
     'Activity_Enviar1doc' => [
        'url' => '/backend/views/enviar_1doc.php',
        'titulo' => 'Mapa comparativo (Grade)'
    ]
];

$config = $bancoDeDadosSimulado[$taskId] ?? null;
// --------------------------------------------------------

// 3. Retorna o resultado
if ($config) {
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => true,
        'url' => $config['url'],
        'titulo' => $config['titulo']
    ]);
} else {
    // Se não achou no banco, retorna erro 404 (Não encontrado)
    // Isso é útil para tarefas que não têm tela (apenas manuais)
    http_response_code(404);
    echo json_encode(['erro' => 'Nenhuma tela configurada para esta etapa']);
}
?>