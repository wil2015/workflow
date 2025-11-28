<?php
// backend/acoes/gerenciar_solicitacao.php
header('Content-Type: application/json');
require '../db_conexao.php'; 

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'vincular') {
        $selecionados = $_POST['selecionados'] ?? [];
        
        if (empty($selecionados)) throw new Exception("Nenhuma solicitação selecionada.");

        // Extrai apenas os números das solicitações (numsol) para criar os processos
        // Formato esperado do value: "CODEMP-NUMSOL" (ex: "1-1050")
        $solicitacoesUnicas = [];
        
        foreach ($selecionados as $item) {
            list($codemp, $numsol) = explode('-', $item);
            // Usa o numsol como chave para garantir unicidade
            $solicitacoesUnicas[$numsol] = [
                'codemp' => $codemp,
                'numsol' => $numsol
            ];
        }

        $pdo->beginTransaction();
        $processosCriados = 0;

        foreach ($solicitacoesUnicas as $sol) {
            $numsol = $sol['numsol'];

            // 1. Verifica se já existe um processo com este ID
            $check = $pdo->prepare("SELECT id FROM processos_instancia WHERE id = ?");
            $check->execute([$numsol]);
            if ($check->fetch()) {
                // Se já existe, pulamos (ou poderíamos lançar erro)
                continue; 
            }

            // 2. Insere Forçando o ID (id = numsol) e (id_processo_senior = numsol)
            // Assumindo id_fluxo_definicao = 1 para Compras
            $sql = "INSERT INTO processos_instancia 
                    (id, id_fluxo_definicao, id_processo_senior, data_inicio, estatus_atual, etapa_bpmn_atual) 
                    VALUES (:id, 1, :id_senior, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $numsol,
                ':id_senior' => $numsol
            ]);
            
            $processosCriados++;
        }

        $pdo->commit();

        if ($processosCriados === 0) {
            echo json_encode(['sucesso' => true, 'msg' => "Nenhum processo novo criado (os itens selecionados já possuíam processos)."]);
        } else {
            echo json_encode(['sucesso' => true, 'msg' => "$processosCriados processo(s) de compras iniciado(s)!"]);
        }

    } elseif ($acao === 'cancelar') {
        // Cancelar agora é deletar o processo diretamente pelo ID (que é o numsol)
        $idProcesso = $_POST['id_processo'];
        
        $stmt = $pdo->prepare("DELETE FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcesso]);

        echo json_encode(['sucesso' => true, 'msg' => "Processo #$idProcesso cancelado."]);

    } else {
        throw new Exception("Ação inválida.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>