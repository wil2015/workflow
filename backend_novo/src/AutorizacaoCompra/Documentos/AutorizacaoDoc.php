<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Documentos;

use AutorizacaoCompra\Dto\AutorizacaoDTO;
use Shared\Documentos\Interfaces\DocumentoInterface;
use Shared\Utils\Formatador;

class AutorizacaoDoc implements DocumentoInterface
{
    private AutorizacaoDTO $dto;

    public function __construct(AutorizacaoDTO $dto)
    {
        $this->dto = $dto;
    }

    public function getAssunto()
    {
        return 'Autorizacao de Compra - Processo ' . $this->dto->numeroProcesso;
    }

    public function getCaminhoTemplate()
    {
        return __DIR__ . '/autorizacao.html.twig';
    }

    public function getDados()
    {
        return [
            'numero_processo' => $this->dto->numeroProcesso,
            'fornecedor_nome' => $this->dto->fornecedorNome,
            'fornecedor_cnpj' => $this->dto->fornecedorCnpj,
            'itens' => $this->dto->itens,
            'valor_total_pedido' => Formatador::moeda($this->dto->valorTotalPedido),
        ];
    }

    public function getNomePastaStorage()
    {
        return 'autorizacao_compra';
    }

    public function getNomeArquivoBase()
    {
        return 'auth_' . $this->dto->idAutorizacao;
    }
}
