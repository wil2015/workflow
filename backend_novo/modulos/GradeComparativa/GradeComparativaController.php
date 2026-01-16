<?php
require '../../db_conexao.php'; // $pdo
require '../../db_senior.php';  // $connSenior

require 'GradeComparativaService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $service = new GradeComparativaService($pdo, $connSenior); 
    
    // Blindagem JSON/POST
    $acao = $_REQUEST['acao'] ?? '';
    if (empty($acao)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json && isset($json['acao'])) {
            $acao = $json['acao'];
            $_POST = array_merge($_POST, $json);
        }
    }

    switch ($acao) {
        case 'carregar_grade':
            $dados = $service->montarGradeParaFront($_GET['instance_id'] ?? 0);
            echo json_encode($dados);
            break;

        case 'consolidar_vencedores':
            $pdo->beginTransaction();
            try {
	                $id = $_POST['id_processo'] ?? 0;
	                $ofertas = $_POST['ofertas'] ?? [];
	                if (!is_array($ofertas)) $ofertas = [];
	                $res = $service->consolidarProcesso($id, $ofertas);
                $pdo->commit();
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            break;

        default:
            throw new Exception("Ação desconhecida no módulo GradeComparativa: '$acao'");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}