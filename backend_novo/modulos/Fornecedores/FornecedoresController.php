<?php
require '../../db_conexao.php'; // $pdo
require '../../db_senior.php';  // $connSenior

require 'FornecedoresService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $service = new FornecedoresService($pdo, $connSenior); 
    
    // --- BLINDAGEM: DETECTOR DE AÇÃO ---
    $acao = '';

    // 1. Tenta POST ou GET padrão
    if (isset($_POST['acao'])) $acao = $_POST['acao'];
    elseif (isset($_GET['acao'])) $acao = $_GET['acao'];

    // 2. Se vazio, tenta ler JSON do corpo da requisição (IMPORTANTE!)
    if (empty($acao)) {
        $jsonBruto = file_get_contents('php://input');
        $dadosJson = json_decode($jsonBruto, true);
        
        if (is_array($dadosJson) && isset($dadosJson['acao'])) {
            $acao = $dadosJson['acao'];
            // Mescla os dados do JSON no $_POST para o resto do código funcionar igual
            $_POST = array_merge($_POST, $dadosJson);
        }
    }
    // ------------------------------------

    if (empty($acao)) {
        throw new Exception("Nenhuma ação recebida pelo servidor.");
    }

    switch ($acao) {
        // --- LEITURA (Sem Transaction) ---
        case 'listar':
            echo json_encode($service->listarParaDatatable($_GET));
            break;

        // --- ESCRITA (Com Transaction) ---
        case 'salvar_lote':
        case 'remover':
            $pdo->beginTransaction();
            try {
                $res = [];
                
                if ($acao === 'salvar_lote') {
                    $res = $service->salvarLote($_POST);
                } 
                elseif ($acao === 'remover') {
                    // Garante a leitura correta dos parâmetros
                    $idProc = $_POST['id_processo'] ?? '';
                    $codForn = $_POST['cod_fornecedor'] ?? '';
                    $res = $service->remover($idProc, $codForn);
                }

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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Retorna erro 500 com JSON explicativo para o Alert do Vue pegar
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}