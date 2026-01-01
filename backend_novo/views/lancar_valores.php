<?php
// backend/views/lancar_valores.php
require_once  'vite_loader.php'; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançar Valores</title>
    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>

    <script>
        // A chave mágica para o App.vue saber o que renderizar
        window.AREA_ATUAL = 'lancamento'; 
        
        window.INSTANCE_ID = "<?= $_GET['instance_id'] ?? '' ?>";
        window.FLUXO_ID = "<?= $_GET['fluxo_id'] ?? '' ?>";
    </script>
</body>
</html>