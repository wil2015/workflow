<?php
// /core/BaseService.php
namespace App\Core; // <--- NOVO

use PDO; // <--- Importante
abstract class BaseService
{
    protected $pdo;
    protected $connSenior;

    public function __construct($pdo, $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    /**
     * Método auxiliar global para corrigir encoding UTF-8
     * Evita duplicar esta função em todos os Services
     */
    protected function utf8($str)
    {
        $str = (string)$str;
        if ($str === '') return '';
        
        // Verifica se JÁ É UTF-8. Se não for...
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            // ...converte explicitamente de ISO-8859-1 para UTF-8
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        return $str;
    }
}