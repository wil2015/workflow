<?php
namespace App\Modulos\ExecucaoFluxo\Handlers;
use App\Core\Interfaces\ActionHandlerInterface;

class RemoverItemHandler implements ActionHandlerInterface {
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function handle(array $payload) {
        return $this->service->removerItem($payload['id'] ?? 0, $payload['num'] ?? 0, $payload['seq'] ?? 0);
    }
}