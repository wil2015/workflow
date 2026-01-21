<?php
require_once __DIR__ . '/ValidacaoAbstrata.php';

class ValidacaoPadrao extends ValidacaoAbstrata {
    // STRATEGY: Implementação concreta da regra
    protected function regrasEspecificas(SalvarDatasDTO $dto) {
        // Regra: Entrega >= Cotação
        if ($dto->dataCotacao && $dto->dataEntrega) {
            if ($dto->dataEntrega < $dto->dataCotacao) {
                throw new Exception("Inconsistência: Data de Entrega menor que Cotação.");
            }
        }
    }
}