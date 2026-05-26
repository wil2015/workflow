<?php
namespace App\Core\Utils;

class Formatador {
    // --- INPUT (Limpeza para o Banco) ---

    public static function moedaParaFloat(?string $valor): ?float {
        if (empty($valor)) return null;
        if (is_numeric($valor)) return (float)$valor;
        // Remove ponto de milhar e troca vírgula por ponto
        $v = str_replace('.', '', $valor);
        return (float)str_replace(',', '.', $v);
    }

    public static function limparTexto(string $texto): string {
        if (empty($texto)) return "";
        // Remove acentos e caracteres especiais
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return strtoupper(trim(preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii)));
    }

    public static function utf8($str) {
        $str = (string)$str;
        if ($str === '') return '';
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        return $str;
    }

    // --- OUTPUT (Formatação para a Tela/PDF) ---

    public static function moeda(?float $valor): string {
        if ($valor === null) return '-';
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public static function numero(?float $valor, int $casas = 2): string {
        return number_format((float)$valor, $casas, ',', '.');
    }

    public static function data($data, $placeholder = '-'): string {
        if (empty($data)) return $placeholder;
        $time = is_string($data) ? strtotime($data) : $data;
        return $time ? date('d/m/Y', $time) : $placeholder;
    }

    public static function dataHora($data, $placeholder = '-'): string {
        if (empty($data)) return $placeholder;
        $time = is_string($data) ? strtotime($data) : $data;
        return $time ? date('d/m/Y H:i', $time) : $placeholder;
    }

    public static function documento(?string $doc): string {
        $doc = preg_replace('/\D/', '', $doc ?? '');
        if (strlen($doc) === 11) { // CPF
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $doc);
        }
        if (strlen($doc) === 14) { // CNPJ
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
        }
        return $doc;
    }
}