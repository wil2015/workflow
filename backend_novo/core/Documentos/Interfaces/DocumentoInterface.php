<?php
// Sem namespace
// Interface que define o que é um documento no sistema
interface DocumentoInterface {
    public function getAssunto();
    public function getCaminhoTemplate(); // Caminho físico do .twig
    public function getDados();           // Array de dados
    public function getNomePastaStorage();
    public function getNomeArquivoBase();
}