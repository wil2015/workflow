<?php
namespace App\Modulos\GradeComparativa\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class ConsolidarVencedoresHandler implements ActionHandlerInterface 
{
    private $service;
    public function __construct($service) { $this->service = $service; }
    
    public function handle(array $payload) {
        return $this->service->consolidarProcesso(
            $payload['id_processo'] ?? 0, 
            $payload['ofertas'] ?? []
        );
    }
}