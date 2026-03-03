<?php

declare(strict_types=1);

namespace Shared\Utils;

class Formatador
{
    // --- INPUT (Limpeza para o Banco) ---

    public static function moedaParaFloat(?string $valor): ?float
    {
        if (empty($valor)) return null;
        if (is_numeric($valor)) return (float)$valor;
        $v = str_replace('.', '', $valor);
        return (float)str_replace(',', '.', $v);
    }

    public static function limparTexto(string $texto): string
    {
        if (empty($texto)) return '';
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return strtoupper(trim(preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii)));
    }

    public static function utf8($str): string
    {
        $str = (string)$str;
        if ($str === '') return '';
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        return $str;
    }

    // --- OUTPUT (Formatacao para a Tela/PDF) ---

    public static function moeda(?float $valor): string
    {
        if ($valor === null) return '-';
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public static function numero(?float $valor, int $casas = 2): string
    {
        return number_format((float)$valor, $casas, ',', '.');
    }

    public static function data($data, string $placeholder = '-'): string
    {
        if (empty($data)) return $placeholder;
        $time = is_string($data) ? strtotime($data) : $data;
        return $time ? date('d/m/Y', $time) : $placeholder;
    }

    public static function dataHora($data, string $placeholder = '-'): string
    {
        if (empty($data)) return $placeholder;
        $time = is_string($data) ? strtotime($data) : $data;
        return $time ? date('d/m/Y H:i', $time) : $placeholder;
    }

    public static function documento(?string $doc): string
    {
        $doc = preg_replace('/\D/', '', $doc ?? '');
        if (strlen($doc) === 11) {
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $doc);
        }
        if (strlen($doc) === 14) {
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
        }
        return $doc;
    }
}
