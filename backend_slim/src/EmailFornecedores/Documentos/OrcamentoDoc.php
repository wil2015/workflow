<?php

declare(strict_types=1);

namespace EmailFornecedores\Documentos;

use Shared\Documentos\Interfaces\DocumentoInterface;

class OrcamentoDoc implements DocumentoInterface
{
    private array $dados;

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    public function getAssunto(): string
    {
        return "Solicitacao de Orcamento - Processo " . ($this->dados['numero_processo'] ?? 'N/A');
    }

    public function getCaminhoTemplate(): string
    {
        return __DIR__ . '/orcamento.html.twig';
    }

    public function getDados(): array
    {
        return $this->dados;
    }

    public function getNomePastaStorage(): string
    {
        return 'solicitacao_orcamento';
    }

    public function getNomeArquivoBase(): string
    {
        return 'orcamento_forn_' . ($this->dados['id_fornecedor'] ?? 'geral');
    }
}
