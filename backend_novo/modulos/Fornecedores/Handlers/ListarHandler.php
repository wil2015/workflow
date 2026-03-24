<?php
namespace App\Modulos\Fornecedores\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class ListarHandler implements ActionHandlerInterface 
{
    private $service;
    public function __construct($service) { $this->service = $service; }
    
    public function handle(array $payload) {
        return $this->service->listarParaDatatable($payload);
    }
}