<?php
// backend/views/selecionar_solicitacoes.php
require_once 'vite_loader.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleção de Solicitações</title>
    
    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>

    <script>
        window.VIEW_DATA = {
            // Aponta para o componente Vue existente
            componente: 'SolicitacoesList.vue', 
            
            // Passa os parâmetros se necessário
            instance_id: "<?= $_GET['instance_id'] ?? '' ?>",
            fluxo_id: "<?= $_GET['fluxo_id'] ?? '' ?>"
        };
    </script>
</body>
</html>