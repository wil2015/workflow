<?php
namespace App\Config;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static $pdo;
    private static $connSenior;

    // Conexão MySQL (Baseada em db_conexao.php)
    public static function getConexao()
    {
        if (!self::$pdo) {
            $mysql_host = "200.145.62.15";
            $mysql_db   = "fundunesp_workflow";
            $mysql_user = "root";
            $mysql_pass = "B*L5gkzgDV@D";
            $dsn = "mysql:host=$mysql_host;dbname=$mysql_db;charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $mysql_user, $mysql_pass);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec("SET NAMES utf8mb4");
            } catch (PDOException $e) {
                die("Erro de conexão MySQL: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    // Conexão Senior SQL Server (Baseada em db_senior.php)
    public static function getSenior()
    {
        if (!self::$connSenior) {
            $sqlserv_host = "200.145.62.23";
            $db_name = "sapiens";
            $db_user = "sapiens";
            $db_pass = "sapiens";
            $dsn = "sqlsrv:Server=$sqlserv_host;Database=$db_name;TrustServerCertificate=1;Encrypt=1";

            try {
                self::$connSenior = new PDO($dsn, $db_user, $db_pass);
                self::$connSenior->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connSenior->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Mantém o comportamento original de retornar JSON em caso de erro crítico
                http_response_code(500);
                die(json_encode(['erro' => "Falha na conexão Senior: " . $e->getMessage()]));
            }
        }
        return self::$connSenior;
    }
}