<?php
// backend/db_senior.php



try {
    // Configura as opções de conexão, incluindo TrustServerCertificate e Encrypt
    $sqlserv_host = "200.145.62.23";
    $connectionOptions = array(
        "Database" => "sapiens",
        "Uid" => "sapiens",
        "PWD" => "sapiens",
        "CharacterSet" => "UTF-8",
        "Encrypt" => true,
        "TrustServerCertificate" => true
    );
    
    // Tenta estabelecer a conexão com o SQL Server
    $connSenior = sqlsrv_connect($sqlserv_host, $connectionOptions);
    
    // Verifica se a conexão falhou
    if ($connSenior === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }

    // Opcional: Para verificar se a conexão foi bem-sucedida
    //echo "Conexão com o banco de dados Sapiens realizada com sucesso!";

} catch (Exception $e) {
    // Se a conexão falhar, exibe o erro e interrompe o script.
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>