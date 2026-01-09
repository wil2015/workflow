<?php
// backend/db_senior.php

$sqlserv_host = "200.145.62.23";
$db_name = "sapiens";
$db_user = "sapiens";
$db_pass = "sapiens";

// --- 1. CONEXÃO LEGADA (sqlsrv_connect) ---
// Mantida para não quebrar o sistema antigo
try {
    $connectionOptions = array(
        "Database" => $db_name,
        "Uid" => $db_user,
        "PWD" => $db_pass,
        "CharacterSet" => "UTF-8",
        "Encrypt" => true,
        "TrustServerCertificate" => true
    );
    
    $connSenior = sqlsrv_connect($sqlserv_host, $connectionOptions);
    
    if ($connSenior === false) {
        // Se falhar o legado, não mata o script ainda, tenta o PDO
        error_log("Erro conexão Legacy Senior: " . print_r(sqlsrv_errors(), true));
    }
} catch (Exception $e) {
    error_log("Erro Fatal Legacy Senior: " . $e->getMessage());
}

// --- 2. NOVA CONEXÃO (PDO) ---
// Usada pelos novos Módulos (Repository)
try {
    // DSN para SQL Server
    $dsn = "sqlsrv:Server=$sqlserv_host;Database=$db_name;TrustServerCertificate=1;Encrypt=1";
    
    $pdoSenior = new PDO($dsn, $db_user, $db_pass);
    $pdoSenior->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoSenior->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro Conexão Senior (PDO): " . $e->getMessage());
}
?>