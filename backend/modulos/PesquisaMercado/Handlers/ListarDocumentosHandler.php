<?php
namespace App\Modulos\PesquisaMercado\Handlers;

use App\Core\Interfaces\ActionHandlerInterface;
use App\Modulos\PesquisaMercado\PesquisaMercadoService;

class ListarDocumentosHandler implements ActionHandlerInterface
{
    private PesquisaMercadoService $service;

    public function __construct(PesquisaMercadoService $service)
    {
        $this->service = $service;
    }

    public function handle(array $payload)
    {
        // Extrai o ID do payload e repassa para o Service
        return $this->service->listarDocumentosGerados($payload['id_processo']);
    }
}