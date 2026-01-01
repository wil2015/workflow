<?php
// backend/views/visualizar_fluxo.php
require_once 'vite_loader.php';

// Captura parâmetros da URL de forma segura
$id = $_GET['id'] ?? '';
$novoXml = $_GET['novo'] ?? '';
$fluxoId = $_GET['fluxo_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Fluxo</title>
    
    <style>
        body, html, #app { height: 100%; margin: 0; padding: 0; overflow: hidden; }
    </style>

    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>

    <script>
        // CONFIGURAÇÃO GLOBAL (Clean Architecture)
        // O Vue lerá este objeto ao iniciar, sem depender de props complexas.
        window.VIEW_DATA = {
            componente: 'BpmnViewer.vue',
            instance_id: "<?= $id ?>",
            novo_xml: "<?= $novoXml ?>",
            fluxo_id: "<?= $fluxoId ?>"
        };
    </script>
</body>
</html>