<?php

class SalvarDatasDTO {
    public int $idProcesso;
    public ?string $dataCotacao;
    public ?string $dataEntrega;

    public function __construct(array $dados) {
        if (empty($dados['id_processo'])) {
            throw new Exception("ID do processo é obrigatório no DTO.");
        }

        $this->idProcesso = (int)$dados['id_processo'];
        
        // Tratamento de string vazia para NULL
        $this->dataCotacao = !empty($dados['data_cotacao']) ? $dados['data_cotacao'] : null;
        $this->dataEntrega = !empty($dados['data_recebimento']) ? $dados['data_recebimento'] : null;
    }
}