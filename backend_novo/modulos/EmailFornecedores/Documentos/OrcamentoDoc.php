<?php
// Caminho: backend_novo/modulos/EmailFornecedores/Documentos/OrcamentoDoc.php

// 1. Importa a Interface do Core (sobe 3 níveis)
/*require_once __DIR__ . '/../../../core/Documentos/Interfaces/DocumentoInterface.php';*/
namespace App\Modulos\EmailFornecedores\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;
class OrcamentoDoc implements DocumentoInterface {
    private $dados;

    public function __construct($dados) {
        $this->dados = $dados;
    }

    public function getAssunto() {
        return "Solicitação de Orçamento - Processo " . ($this->dados['numero_processo'] ?? 'N/A');
    }

    public function getCaminhoTemplate() {
        // Aponta para o arquivo .twig NESTA MESMA PASTA
        return __DIR__ . '/orcamento.html.twig';
    }

    public function getDados() {
        return $this->dados;
    }

    public function getNomePastaStorage() {
        // Define a subpasta onde os PDFs ficarão salvos no servidor
        return 'solicitacao_orcamento';
    }

    public function getNomeArquivoBase() {
        // Ex: orcamento_forn_43006
        return 'orcamento_forn_' . ($this->dados['id_fornecedor'] ?? 'geral');
    }
}