<?php
// --- 1. Conexão com MySQL ---
$mysql_host = "200.145.62.15"; // Host do MySQL
$mysql_db   = "fundunesp_workflow"; // Seu banco de dados MySQL
$mysql_user = "root";
$mysql_pass = "B*L5gkzgDV@D"; // Sua senha do MySQL
$mysql_dsn = "mysql:host=$mysql_host;dbname=$mysql_db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];


try {
    // Tenta conectar ao MySQL
    $pdo = new PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    
        
} catch (PDOException $e) {
    // Se qualquer uma das conexões falhar, exibe o erro e interrompe o script.
    // Em um ambiente de produção, você pode querer logar o erro em vez de exibi-lo.
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>