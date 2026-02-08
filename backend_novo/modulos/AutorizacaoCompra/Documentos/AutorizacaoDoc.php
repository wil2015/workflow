<?php
/*require_once __DIR__ . '/../../../core/Documentos/Interfaces/DocumentoInterface.php';*/
namespace App\Modulos\AutorizacaoCompra\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;
class AutorizacaoDoc implements DocumentoInterface {
    private $dados;

    public function __construct($dados) {
        $this->dados = $dados;
    }

    public function getAssunto() {
        return "Autorização de Compra - Processo " . ($this->dados['numero_processo'] ?? 'N/A');
    }

    public function getCaminhoTemplate() {
        // Aponta para o arquivo na MESMA PASTA
        return __DIR__ . '/autorizacao.html.twig';
    }

    public function getDados() {
        return $this->dados;
    }

    public function getNomePastaStorage() {
        return 'autorizacao_compra';
    }

    public function getNomeArquivoBase() {
        return 'auth_' . ($this->dados['id_autorizacao'] ?? date('Ymd'));
    }
}