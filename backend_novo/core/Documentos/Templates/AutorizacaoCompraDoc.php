<?php
// Supondo que DocumentoBase e HtmlBuilder estejam no Core

require_once __DIR__ . '/../Engine/DocumentoBase.php';
require_once __DIR__ . '/../Engine/HtmlBuilder.php';
class AutorizacaoCompraDoc extends DocumentoBase {

    protected function montarCorpo(): string {
        $d = $this->dados; // Recebe o array preparado pelo Service
        $html = "";

        // --- Layout baseado no PDF Fundunesp ---

        $html .= "<div class='text-right'>São Paulo, {$d['data_emissao_extenso']}</div>";
        $html .= "<div class='text-center bold' style='margin-top:15px; font-size:14px; text-decoration:underline'>AUTORIZAÇÃO DE COMPRA Nº {$d['numero_autorizacao']}</div>";
        $html .= "<div class='text-center' style='margin-bottom:20px; font-size:10px;'>(Regulamento de Compras da Fundunesp)</div>";

        // Caixa Destinatário
        $html .= "<div class='box-info' style='background-color:#fff;'>";
        $html .= "<strong>À: {$d['fornecedor_nome']}</strong><br>";
        $html .= "Endereço: {$d['fornecedor_endereco']}<br>";
        $html .= "CNPJ: {$d['fornecedor_cnpj']} - Telefone: {$d['fornecedor_fone']}<br>";
        $html .= "</div>";

        // Texto Introdutório
        $html .= "<p><strong>Ref. Processo:</strong> {$d['processo_numero']} ({$d['fluxo_nome']})</p>";
        $html .= "<p>Conforme sua proposta aprovada, vimos autorizar a entrega do produto conforme segue:</p>";

        // Tabela de Itens
        $colunas = [
            ['label' => '#', 'key' => 'seq', 'width' => '5%', 'align' => 'center'],
            ['label' => 'Descrição', 'key' => 'descricao', 'width' => '55%'],
            ['label' => 'Unid.', 'key' => 'unidade', 'width' => '10%', 'align' => 'center'],
            ['label' => 'Qtd', 'key' => 'quantidade', 'width' => '8%', 'align' => 'right', 'type' => 'number'],
            ['label' => 'Vlr. Unit.', 'key' => 'valor_unitario', 'width' => '11%', 'align' => 'right', 'type' => 'money'],
            ['label' => 'Total', 'key' => 'valor_total', 'width' => '11%', 'align' => 'right', 'type' => 'money'],
        ];

        $html .= HtmlBuilder::tabela($colunas, $d['itens']);
        
        $total = number_format($d['total_geral'], 2, ',', '.');
        $html .= "<div class='text-right bold' style='margin-top:5px;'>VALOR TOTAL: R$ {$total}</div>";

        // Condições Gerais (Texto Fixo)
        $html .= "
        <br>
        <h4>Condições Gerais:</h4>
        <ul style='font-size:10px;'>
            <li><strong>Entrega:</strong> Imediata / Conforme edital.</li>
            <li><strong>Pagamento:</strong> 21 dias após entrega definitiva e aceite da NF.</li>
            <li><strong>Local:</strong> {$d['local_entrega_completo']}</li>
        </ul>
        
        <br><br><br>
        
        <table width='100%'>
            <tr>
                <td width='50%' align='center' style='border:none;'>
                    _______________________________<br>
                    <strong>Departamento de Compras</strong>
                </td>
                <td width='50%' align='center' style='border:none;'>
                    _______________________________<br>
                    <strong>Fornecedor (De Acordo)</strong>
                </td>
            </tr>
        </table>
        ";

        return $html;
    }
}