<?php
namespace App\Modulos\ExecucaoFluxo\Handlers;
use App\Core\Interfaces\ActionHandlerInterface;

class SalvarDatasHandler implements ActionHandlerInterface {
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function handle(array $payload) {
        return $this->service->salvarDatasPrevisao($payload);
    }
}