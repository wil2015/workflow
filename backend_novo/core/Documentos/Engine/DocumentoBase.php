<?php
// backend/core/Documentos/Engine/DocumentoBase.php

// Ajuste este caminho conforme onde está sua pasta vendor. 
// Se estiver na raiz do backend:
require_once __DIR__ . '/../../../vendor/autoload.php'; 

abstract class DocumentoBase {
    protected $mpdf;
    protected $dados;

    public function __construct($dados) {
        $this->dados = $dados;
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 30,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);
        
        $this->definirEstilos();
        $this->configurarCabecalhoRodape();
    }

    public function renderizar($nomeArquivo, $destino = 'I') {
        $html = $this->montarCorpo(); 
        $this->mpdf->WriteHTML($html);
        return $this->mpdf->Output($nomeArquivo, $destino);
    }

    abstract protected function montarCorpo(): string;

    private function definirEstilos() {
        $css = "body { font-family: Arial, sans-serif; font-size: 11px; } 
                .table-padrao { width: 100%; border-collapse: collapse; } 
                .table-padrao td, .table-padrao th { border: 1px solid #000; padding: 5px; }";
        $this->mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    }

    private function configurarCabecalhoRodape() {
        $this->mpdf->SetFooter('Pág {PAGENO}/{nbpg}');
    }
}