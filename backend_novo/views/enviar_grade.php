<?php
// backend/views/enviar_grade.php
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Comparativa</title>
    
    <script type="module" src="http://localhost:5174/@vite/client"></script>
    <script type="module" src="http://localhost:5174/src/main.js"></script>
</head>
<body>
    <div id="app"></div>

    <script>
        window.AREA_ATUAL = 'grade'; 
        window.INSTANCE_ID = "<?= $_GET['instance_id'] ?? '' ?>";
    </script>
</body>
</html>