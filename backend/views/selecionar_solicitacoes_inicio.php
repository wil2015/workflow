<?php
// SIMULAÇÃO DE CONEXÃO COM SQL SERVER
// $conn = sqlsrv_connect($serverName, $connectionInfo);
// $sql = "SELECT * FROM E001SOL WHERE SITUACAO = 'PENDENTE'";

// Simulando dados retornados
$solicitacoes = [
    ['id' => 101, 'depto' => 'TI', 'item' => 'Notebook Dell', 'valor' => '5.000,00'],
    ['id' => 102, 'depto' => 'RH', 'item' => 'Cadeiras Ergonômicas', 'valor' => '2.500,00'],
    ['id' => 103, 'depto' => 'MKT', 'item' => 'Monitor 4K', 'valor' => '1.800,00'],
];
?>

<h2>Selecionar Solicitações (SQL Server)</h2>
<p>Selecione as solicitações para iniciar o processo de compra.</p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Departamento</th>
            <th>Item</th>
            <th>Valor Est.</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($solicitacoes as $sol): ?>
        <tr>
            <td><?= $sol['id'] ?></td>
            <td><?= $sol['depto'] ?></td>
            <td><?= $sol['item'] ?></td>
            <td>R$ <?= $sol['valor'] ?></td>
            <td><button class="btn-selecionar" onclick="alert('Solicitação <?= $sol['id'] ?> vinculada!')">Vincular</button></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>