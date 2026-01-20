<?php
// backend/db_senior.php

$sqlserv_host = "200.145.62.23";
$db_name = "sapiens";
$db_user = "sapiens";
$db_pass = "sapiens";

try {
    // DSN para SQL Server
    // Ajuste: Adicionei charset=UTF-8 se necessário, mas o padrão costuma funcionar
    $dsn = "sqlsrv:Server=$sqlserv_host;Database=$db_name;TrustServerCertificate=1;Encrypt=1";
    
    // --- AQUI ESTAVA O ERRO: Mudamos de $pdoSenior para $connSenior ---
    $connSenior = new PDO($dsn, $db_user, $db_pass);
    
    // Configurações vitais para o PDO funcionar bem
    $connSenior->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connSenior->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Retorna erro 500 para o DataTables pegar no Javascript
    http_response_code(500);
    // Mata o script retornando JSON válido de erro
    die(json_encode(['erro' => "Falha na conexão Senior (PDO): " . $e->getMessage()]));
}
?>