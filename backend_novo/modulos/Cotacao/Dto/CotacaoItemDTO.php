<?php
namespace App\Modulos\Cotacao\Dto;

use App\Core\Utils\Formatador;

class CotacaoItemDTO {
    public ?int $idFornecedor;
    public ?float $valorUnitario;

    public function __construct(array $input) {
        $this->idFornecedor = isset($input['cod']) ? (int)$input['cod'] : null;
        // Helper converte "1.000,00" para 1000.00
        $this->valorUnitario = Formatador::moedaParaFloat($input['valor'] ?? null);
    }

    public function isValido(): bool {
        return $this->idFornecedor > 0;
    }
}