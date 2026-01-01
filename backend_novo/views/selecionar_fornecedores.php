<?php
// backend/views/selecionar_fornecedores.php
require_once 'vite_loader.php'; // Usa o carregador inteligente que criamos
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Fornecedores</title>
    
    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>

    <script>
        // CONFIGURAÇÃO NOVA (Para a Arquitetura Limpa)
        window.VIEW_DATA = {
            // O Vue lê isso e carrega o arquivo 'FornecedoresList.vue' automaticamente
            componente: 'FornecedoresList.vue', 
            
            // Parâmetros mantidos
            instance_id: "<?= $_GET['instance_id'] ?? '' ?>",
            fluxo_id: "<?= $_GET['fluxo_id'] ?? '' ?>"
        };
    </script>
</body>
</html>