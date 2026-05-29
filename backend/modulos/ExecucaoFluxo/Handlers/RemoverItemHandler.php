<?php
namespace App\Modulos\ExecucaoFluxo\Handlers;
use App\Core\Interfaces\ActionHandlerInterface;

class RemoverItemHandler implements ActionHandlerInterface {
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function handle(array $payload) {
        return $this->service->removerItem(
            $payload['id_processo'] ?? $payload['id'] ?? 0,
            $payload['numero_oc'] ?? $payload['num_solicitacao'] ?? $payload['num'] ?? 0,
            $payload['sequencia_oc'] ?? $payload['seq_solicitacao'] ?? $payload['seq'] ?? 0
        );
    }
}
