<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Dto;

use Shared\Utils\Formatador;

class AutorizacaoDTO
{
    public int $idAutorizacao;
    public string $numeroProcesso;
    public string $fornecedorNome;
    public string $fornecedorCnpj;
    public ?float $valorTotalPedido;
    public array $itens = [];

    public function __construct(int $id, string $proc, string $nome, string $cnpj, ?float $total)
    {
        $this->idAutorizacao = $id;
        $this->numeroProcesso = $proc;
        $this->fornecedorNome = $nome ?: 'Consumidor';
        $this->fornecedorCnpj = Formatador::documento($cnpj);
        $this->valorTotalPedido = $total;
    }

    public function addItem($desc, $qtd, $valUnit, $valTotal): void
    {
        $this->itens[] = [
            'descricao' => $desc,
            'quantidade' => Formatador::numero((float)$qtd),
            'valor' => Formatador::moeda((float)$valUnit),
            'valor_total' => Formatador::moeda((float)$valTotal),
        ];
    }
}
