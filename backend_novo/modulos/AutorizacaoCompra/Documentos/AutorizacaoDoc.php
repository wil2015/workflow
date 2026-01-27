<?php
// Caminho: backend_novo/modulos/AutorizacaoCompra/Documentos/AutorizacaoDoc.php
// Objetivo: Chegar em core/Documentos/Interfaces/DocumentoInterface.php

// __DIR__ = Documentos
// ../    = AutorizacaoCompra
// ../../ = modulos
// ../../../ = backend_novo

require_once __DIR__ . '/../../../core/Documentos/Interfaces/DocumentoInterface.php';

class AutorizacaoDoc implements DocumentoInterface {
    private $dados;

    public function __construct($dados) {
        $this->dados = $dados;
    }

    public function getAssunto() {
        return "Autorização de Compra #" . $this->dados['id'];
    }

    public function getCaminhoTemplate() {
        // O arquivo .twig está na mesma pasta que este arquivo .php
        return __DIR__ . '/autorizacao.html.twig';
    }

    public function getDados() {
        return $this->dados;
    }

    public function getNomePastaStorage() {
        return 'autorizacao_compra';
    }

    public function getNomeArquivoBase() {
        return 'auth_' . $this->dados['id'];
    }
}