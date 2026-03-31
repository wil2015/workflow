<?php

declare(strict_types=1);

namespace Shared\Documentos\Interfaces;

interface DocumentoInterface
{
    public function getAssunto(): string;
    public function getCaminhoTemplate(): string;
    public function getDados(): array;
    public function getNomePastaStorage(): string;
    public function getNomeArquivoBase(): string;
}
