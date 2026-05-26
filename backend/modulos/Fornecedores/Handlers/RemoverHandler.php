<?php
namespace App\Modulos\Fornecedores\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;

class RemoverHandler implements ActionHandlerInterface 
{
    private $service;
    public function __construct($service) { $this->service = $service; }
    
    public function handle(array $payload) {
        return $this->service->remover(
            $payload['id_processo'] ?? '', 
            $payload['cod_fornecedor'] ?? ''
        );
    }
}