<?php
namespace App\Modulos\ExecucaoFluxo\Handlers;
use App\Core\Interfaces\ActionHandlerInterface;

class ListarSolicitacoesHandler implements ActionHandlerInterface {
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function handle(array $payload) {
        return $this->service->listarSolicitacoesSenior($payload['request']);
    }
}