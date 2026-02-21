<?php

declare(strict_types=1);

namespace Shared\Documentos\Interfaces;

interface DocumentoInterface
{
    public function getAssunto();

    public function getCaminhoTemplate();

    public function getDados();

    public function getNomePastaStorage();

    public function getNomeArquivoBase();
}
