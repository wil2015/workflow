<?php
// backend/views/selecionar_solicitacoes.php

require  '../db_conexao.php';
require  '../db_senior.php';

// 1. Busca quais NUMSOL já viraram processos no MySQL
// Retorna array onde a chave é o ID do processo (que é o numsol)
$processosExistentes = [];
try {
    // Pegamos apenas o ID, pois ID = NUMSOL agora
    $stmt = $pdo->query("SELECT id FROM processos_instancia WHERE id_fluxo_definicao = 1"); // 1 = Compras
    $processosExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE, 0); 
    // Resultado: [ 1050 => 1050, 1051 => 1051 ]
} catch (Exception $e) { }

// 2. Busca solicitações no Senior
$listaSolicitacoes = [];
if ($connSenior) {
    // Consulta Real
    $sql = "SELECT TOP 50 codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed 
            FROM Sapiens.sapiens.e405sol 
            WHERE sitsol IN (1, 2) ORDER BY numsol DESC, seqsol ASC";
    $stmtSenior = sqlsrv_query($connSenior, $sql);
    if ($stmtSenior) {
        while ($row = sqlsrv_fetch_array($stmtSenior, SQLSRV_FETCH_ASSOC)) {
            $listaSolicitacoes[] = $row;
        }
    }
} else {
    // Simulação
    $listaSolicitacoes = [
        ['codemp'=>1, 'numsol'=>1050, 'seqsol'=>1, 'cplpro'=>'Notebook Dell', 'qtdsol'=>2, 'presol'=>15000, 'unimed'=>'UN'],
        ['codemp'=>1, 'numsol'=>1050, 'seqsol'=>2, 'cplpro'=>'Mouse USB', 'qtdsol'=>2, 'presol'=>100, 'unimed'=>'UN'],
        ['codemp'=>1, 'numsol'=>1051, 'seqsol'=>1, 'cplpro'=>'Cadeira Office', 'qtdsol'=>5, 'presol'=>4500, 'unimed'=>'UN'],
    ];
}
?>

<div class="modal-header">
    <h2>Vincular Solicitações (ID Processo = Nº Solicitação)</h2>
</div>

<div class="tabela-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th width="40"></th>
                <th>Solicitação</th>
                <th>Seq.</th>
                <th>Produto</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listaSolicitacoes as $sol): 
                $numsol = $sol['numsol'];
                // Verifica se este numsol já é um ID na tabela de processos
                $existeProcesso = isset($processosExistentes[$numsol]);
                
                // Chave composta apenas para o value do checkbox (para o PHP separar depois)
                $chaveEnvio = $sol['codemp'] . '-' . $numsol;
            ?>
            <tr class="<?= $existeProcesso ? 'row-disabled' : '' ?>">
                <td class="text-center">
                    <?php if ($existeProcesso): ?>
                        <button class="btn-cancel-mini" onclick="cancelarProcesso(<?= $numsol ?>)" title="Cancelar Processo #<?= $numsol ?>">
                            &times;
                        </button>
                    <?php else: ?>
                        <input type="checkbox" name="selecionados[]" value="<?= $chaveEnvio ?>" class="chk-item">
                    <?php endif; ?>
                </td>
                <td><b><?= $numsol ?></b></td>
                <td><?= $sol['seqsol'] ?></td>
                <td><?= $sol['cplpro'] ?></td>
                <td>
                    <?php if ($existeProcesso): ?>
                        <span class="badge badge-warning">Em Processo</span>
                    <?php else: ?>
                        <span class="badge badge-success">Novo</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-footer">
    <button class="btn-cancel" onclick="document.getElementById('modal-overlay').classList.add('hidden')">Fechar</button>
    <button class="btn-save" onclick="vincularSelecionados()">Gerar Processos</button>
</div>