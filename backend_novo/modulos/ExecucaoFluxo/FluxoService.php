<?php
require_once __DIR__ . '/../../core/BaseService.php';
// Os Requires do Repo e Strategy agora são responsabilidade da Factory ou Autoloader

class FluxoService extends BaseService
{
    private $repo;
    private $validador; // Injeção da Strategy

    // Recebe o Repo e a Estratégia de Validação prontos
    public function __construct(FluxoRepo $repo, ValidacaoAbstrata $validador) {
        // O BaseService esperava ($pdo, $connSenior), mas como estamos usando Repo pattern, 
        // podemos passar null para o pai ou refatorar o BaseService no futuro. 
        // Por compatibilidade, vamos manter o parent construct simples se possível, 
        // ou ignorá-lo se ele só setava variáveis que não usamos mais diretamente aqui.
        $this->repo = $repo;
        $this->validador = $validador;
    }

    // ... [Métodos de Leitura carregarPassoAtual mantidos igual] ...

    // MÉTODO REFATORADO COM DTO E STRATEGY
    public function salvarDatasPrevisao(SalvarDatasDTO $dto) {
        
        // 1. Usa a Strategy para validar (Sem if/else aqui dentro)
        $this->validador->validar($dto);

        // 2. Chama o Repo
        $this->repo->atualizarDatasPrevisao(
            $dto->idProcesso, 
            $dto->dataCotacao, 
            $dto->dataEntrega
        );

        return ['sucesso' => true, 'msg' => 'Datas atualizadas com sucesso!'];
    }

    // ... [Outros métodos mantidos] ...
}