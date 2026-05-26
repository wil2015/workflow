<?php
namespace App\Modulos\PesquisaMercado\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;

class PesquisaMercadoDoc implements DocumentoInterface
{
    private $dados;

    public function __construct(array $dados) { $this->dados = $dados; }

    public function getAssunto() { return "Pesquisa de Mercado - Processo " . $this->dados['processo']; }
    public function getCaminhoTemplate() { return __DIR__ . '/pesquisa_mercado.html.twig'; }
    public function getDados() { return $this->dados; }
    public function getNomePastaStorage() { return 'pesquisas_mercado'; }
    public function getNomeArquivoBase() { return "pesquisa_" . $this->dados['id_processo']; }
}