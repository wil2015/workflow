<?php

abstract class ValidacaoAbstrata {
    // TEMPLATE METHOD: Define o passo a passo da validação
    public function validar(SalvarDatasDTO $dto) {
        $this->validarFormatoDatas($dto); // Passo 1: Comum a todos
        $this->regrasEspecificas($dto);   // Passo 2: Estratégia específica
    }

    private function validarFormatoDatas(SalvarDatasDTO $dto) {
        $hoje = date('Y-m-d');
        
        if ($dto->dataCotacao && $dto->dataCotacao < $hoje) {
            throw new Exception("Data de Cotação não pode ser no passado.");
        }
        if ($dto->dataEntrega && $dto->dataEntrega < $hoje) {
            throw new Exception("Data de Entrega não pode ser no passado.");
        }
    }

    // Este método DEVE ser implementado pelas classes filhas
    abstract protected function regrasEspecificas(SalvarDatasDTO $dto);
}