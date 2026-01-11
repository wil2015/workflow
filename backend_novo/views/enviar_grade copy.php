<?php
// backend/views/enviar_grade.php
require_once 'vite_loader.php'; // Usa o carregador padrão
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Comparativa</title>
    
    <?= carregar_cabecalho_vue() ?>
</head>
<body>
    <div id="app"></div>

    <script>
        // CONFIGURAÇÃO NOVA
        window.VIEW_DATA = {
            // Aponta para o arquivo Vue da Grade
            componente: 'GradeComparativa.vue', 
            
            // Passa o ID do processo
            instance_id: "<?= $_GET['instance_id'] ?? '' ?>"
        };
    </script>
</body>
</html>