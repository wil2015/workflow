<?php
namespace App\Modulos\AutorizacaoCompra\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;
use App\Modulos\AutorizacaoCompra\Dto\AutorizacaoDTO;
use App\Core\Utils\Formatador;

class AutorizacaoDoc implements DocumentoInterface {
    private AutorizacaoDTO $dto;

    public function __construct(AutorizacaoDTO $dto) {
        $this->dto = $dto;
    }

    public function getAssunto() {
        return "Autorização de Compra - Processo " . $this->dto->numeroProcesso;
    }

    public function getCaminhoTemplate() {
        return __DIR__ . '/autorizacao.html.twig';
    }

    public function getDados() {
        // Converte DTO para array simples pro Twig
        return [
            'numero_processo' => $this->dto->numeroProcesso,
            'fornecedor_nome' => $this->dto->fornecedorNome,
            'fornecedor_cnpj' => $this->dto->fornecedorCnpj,
            'itens' => $this->dto->itens,
            'valor_total_pedido' => Formatador::moeda($this->dto->valorTotalPedido)
        ];
    }

    public function getNomePastaStorage() { return 'autorizacao_compra'; }
    public function getNomeArquivoBase() { return 'auth_' . $this->dto->idAutorizacao; }
}