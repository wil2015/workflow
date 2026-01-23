<?php
class HtmlBuilder {
    
    public static function tabela(array $headers, array $rows, array $config = []) {
        $html = "<table class='table-padrao' cellspacing='0' cellpadding='0'>";
        
        // Cabeçalho
        $html .= "<thead><tr>";
        foreach ($headers as $h) {
            $width = isset($h['width']) ? "width:{$h['width']};" : "";
            $align = isset($h['align']) ? "text-align:{$h['align']};" : "text-align:left;";
            $html .= "<th style='{$width} {$align}'>{$h['label']}</th>";
        }
        $html .= "</tr></thead><tbody>";

        // Linhas
        foreach ($rows as $row) {
            $html .= "<tr>";
            foreach ($headers as $h) {
                $key = $h['key'];
                $val = $row[$key] ?? '';
                $align = isset($h['align']) ? "text-align:{$h['align']};" : "text-align:left;";
                
                // Formatação automática
                if (isset($h['type']) && $h['type'] == 'money') {
                    $val = 'R$ ' . number_format((float)$val, 2, ',', '.');
                }
                
                $html .= "<td style='{$align}'>{$val}</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }

    public static function caixaDestaque($titulo, $conteudo) {
        return "
        <div class='box-destaque'>
            <div class='box-title'>$titulo</div>
            <div class='box-content'>$conteudo</div>
        </div>";
    }
}