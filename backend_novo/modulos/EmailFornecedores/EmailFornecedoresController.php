<?php
require '../../db_conexao.php'; 
require '../../db_senior.php';

require 'EmailFornecedoresService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($connSenior)) throw new Exception("Conexão Senior não definida.");
    $service = new EmailFornecedoresService($pdo, $connSenior);

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    $params = array_merge($_REQUEST, (array)$input);
    
    $acao = $params['acao'] ?? '';

    switch ($acao) {
        case 'listar':
            $id = $params['instance_id'] ?? 0;
            $dados = $service->carregarEmails($id);
            echo json_encode($dados);
            break;

        case 'salvar':
            $pdo->beginTransaction();
            try {
                $id = $params['instance_id'] ?? 0;
                $selecionados = $params['emails_selecionados'] ?? [];
                $res = $service->salvarEmailsSelecionados($id, $selecionados);
                $pdo->commit();
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            break;

        // --- NOVA AÇÃO: ADICIONAR EMAIL MANUAL ---
        case 'add_email':
            // Não precisa de transaction pois é um insert simples
            $cod = $params['id_fornecedor_senior'] ?? 0;
            $email = $params['email'] ?? '';
            
            $res = $service->adicionarEmailManual($cod, $email);
            echo json_encode($res);
            break;

        default:
            throw new Exception("Ação desconhecida: $acao");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>