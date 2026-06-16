<?php
namespace App\Modulos\Parametros\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class SalvarParametrosHandler implements ActionHandlerInterface {
    private $service;
    public function __construct($service) { $this->service = $service; }
    public function handle(array $payload) {
        return $this->service->salvar($payload);
    }
}
