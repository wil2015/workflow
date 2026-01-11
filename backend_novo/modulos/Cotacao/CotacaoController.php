<?php
require '../../db_conexao.php'; // $pdo
require '../../db_senior.php';  // $connSenior

require 'CotacaoService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $service = new CotacaoService($pdo, $connSenior); 
    
    // Blindagem de leitura JSON/POST
    $acao = $_REQUEST['acao'] ?? '';
    if (empty($acao)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json && isset($json['acao'])) {
            $acao = $json['acao'];
            $_POST = array_merge($_POST, $json);
        }
    }

    switch ($acao) {
        case 'listar_itens':
            echo json_encode($service->listarItensComDetalhes($_GET['instance_id'] ?? 0));
            break;

        case 'listar_cotacoes':
            echo json_encode($service->buscarCotacoesDoItem($_GET));
            break;

        // --- MUDANÇA: AGORA USAMOS SALVAR LOTE ---
        case 'salvar_lote':
            $pdo->beginTransaction();
            try {
                // O Service agora processa o array inteiro
                $res = $service->salvarLote($_POST);
                $pdo->commit();
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            break;

        default:
            throw new Exception("Ação desconhecida: '$acao'");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}