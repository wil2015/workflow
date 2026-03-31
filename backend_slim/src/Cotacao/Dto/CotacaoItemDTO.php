<?php

declare(strict_types=1);

namespace Cotacao\Dto;

use Shared\Utils\Formatador;

class CotacaoItemDTO
{
    public ?int $idFornecedor;
    public ?float $valorUnitario;

    public function __construct(array $input)
    {
        $this->idFornecedor = isset($input['cod']) ? (int)$input['cod'] : null;
        $this->valorUnitario = Formatador::moedaParaFloat($input['valor'] ?? null);
    }

    public function isValido(): bool
    {
        return $this->idFornecedor > 0;
    }
}
