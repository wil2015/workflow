<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use App\Handler\HandlerHelper;
use ExecucaoFluxo\Service\FluxoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class LerTarefaHandler
{
    use HandlerHelper;

    private FluxoService $service;

    public function __construct(FluxoService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $idInstancia = $args['id'] ?? null;
            $result = $this->service->carregarPassoAtual($idInstancia);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
