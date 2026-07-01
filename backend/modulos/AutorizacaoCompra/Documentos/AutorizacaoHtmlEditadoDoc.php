<?php
namespace App\Modulos\AutorizacaoCompra\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;

class AutorizacaoHtmlEditadoDoc implements DocumentoInterface
{
    private string $idAutorizacao;
    private string $numeroProcesso;
    private string $htmlDocumento;

    public function __construct(string $idAutorizacao, string $numeroProcesso, string $htmlDocumento)
    {
        $this->idAutorizacao = $idAutorizacao;
        $this->numeroProcesso = $numeroProcesso;
        $this->htmlDocumento = $htmlDocumento;
    }

    public function getAssunto()
    {
        return "Autorização de Compra - Processo " . $this->numeroProcesso;
    }

    public function getCaminhoTemplate()
    {
        return __DIR__ . '/autorizacao_editada.html.twig';
    }

    public function getDados()
    {
        return [
            'html_documento' => $this->htmlDocumento
        ];
    }

    public function getNomePastaStorage()
    {
        return 'autorizacao_compra';
    }

    public function getNomeArquivoBase()
    {
        // Mantém o padrão auth_{id} para o envio de e-mail continuar encontrando o arquivo.
        return 'auth_' . $this->normalizarIdArquivo($this->idAutorizacao);
    }

    private function normalizarIdArquivo(string $idAutorizacao): string
    {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $idAutorizacao), '_');
    }
}
