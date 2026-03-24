<?php
namespace App\Modulos\Dashboard\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class HomeHandler implements ActionHandlerInterface 
{
    private $service;

    public function __construct($service) 
    { 
        $this->service = $service; 
    }

    public function handle(array $payload) 
    {
        // Como o 'home' não precisa de parâmetros complexos no momento,
        // apenas chamamos o método do service.
        return $this->service->carregarHome();
    }
}