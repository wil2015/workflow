<?php
namespace App\Modulos\PesquisaMercado\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;
use App\Modulos\PesquisaMercado\PesquisaMercadoService;

class GerarPdfHandler implements ActionHandlerInterface
{
    private PesquisaMercadoService $service;

    public function __construct(PesquisaMercadoService $service)
    {
        $this->service = $service;
    }

    public function handle(array $payload)
    {
        return $this->service->gerarRelatorioPesquisa(
            $payload['id_processo'], 
            $payload['id_usuario']
        );
    }
}