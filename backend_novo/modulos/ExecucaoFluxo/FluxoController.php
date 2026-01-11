<?php
require '../../db_conexao.php'; // $pdo
require '../../db_senior.php';  // $connSenior

require 'FluxoService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Injeta as dependências
    $service = new FluxoService($pdo, $connSenior); 
    
    $acao = $_REQUEST['acao'] ?? '';

    switch ($acao) {
        // --- LEITURA (Não precisa de transaction) ---
        case 'ler_tarefa':
            $id = $_GET['id_instancia'] ?? null;
            if (!$id) throw new Exception("ID não informado");
            
            $dados = $service->carregarPassoAtual($id);
            echo json_encode($dados);
            break;

        case 'listar_solicitacoes':
            echo json_encode($service->listarSolicitacoesSenior($_GET));
            break;

        // --- ESCRITA (BLINDADA COM TRANSACTION) ---
        
        case 'vincular':
            $pdo->beginTransaction(); // <--- Inicia
            try {
                $res = $service->vincularItens($_POST);
                $pdo->commit();       // <--- Confirma se tudo der certo
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();     // <--- Desfaz se der erro
                throw $ex;
            }
            break;

        case 'remover_item':
            $pdo->beginTransaction();
            try {
                $res = $service->removerItem($_POST['id_processo'], $_POST['num_solicitacao'], $_POST['seq_solicitacao']);
                $pdo->commit();
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            break;

        case 'cancelar_processo':
            $pdo->beginTransaction(); // <--- AGORA PROTEGIDO
            try {
                $idProcesso = $_POST['id_processo'] ?? null;
                if(!$idProcesso) throw new Exception("ID do processo não enviado.");

                $res = $service->cancelarProcesso($idProcesso);
                
                $pdo->commit();       // <--- Confirma a exclusão em cascata
                echo json_encode($res);
            } catch (Exception $ex) {
                $pdo->rollBack();     // <--- Se falhar, desfaz tudo
                throw $ex;
            }
            break;

        default:
            throw new Exception("Ação desconhecida: " . $acao);
    }

} catch (Exception $e) {
    // Se o erro subir até aqui e a transação estiver aberta, faz rollback de segurança
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}