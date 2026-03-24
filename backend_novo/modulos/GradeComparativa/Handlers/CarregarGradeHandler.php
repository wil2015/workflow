<?php
namespace App\Modulos\GradeComparativa\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class CarregarGradeHandler implements ActionHandlerInterface 
{
    private $service;
    public function __construct($service) { $this->service = $service; }
    
    public function handle(array $payload) {
        return $this->service->montarGradeParaFront($payload['instance_id'] ?? 0);
    }
}