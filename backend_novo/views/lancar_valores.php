<?php
// backend/views/lancar_valores.php
require_once 'vite_loader.php';
$id = $_GET['instance_id'] ?? '';
$fluxo = $_GET['fluxo_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Processo</title>
    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>
    <script>
        // CORREÇÃO: Apontamos para o arquivo físico
        window.VIEW_DATA = {
            componente: 'CotacaoValores.vue',
            instance_id: "<?= $id ?>",
            fluxo_id: "<?= $fluxo ?>"
        };
    </script>
</body>
</html>