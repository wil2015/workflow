<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Fornecedores</title>
    
      
    <script type="module" src="http://localhost:5174/src_novo/main.js"></script>

    
    <script type="module" src="http://localhost:5174/@vite/client"></script>
    </head>
<body>
    <div id="app"></div>

    <script>
        // Esta variável diz para o App.vue qual tela desenhar
        window.AREA_ATUAL = 'fornecedores'; 
        
        // Passamos os IDs da URL (que vieram do router.php) para o JS global
        window.INSTANCE_ID = "<?= $_GET['instance_id'] ?? '' ?>";
        window.FLUXO_ID = "<?= $_GET['fluxo_id'] ?? '' ?>";
    </script>
</body>
</html>