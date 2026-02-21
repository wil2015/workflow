<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use App\Handler\HandlerHelper;
use ExecucaoFluxo\Service\FluxoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ListarSolicitacoesHandler
{
    use HandlerHelper;

    private FluxoService $service;

    public function __construct(FluxoService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $result = $this->service->listarSolicitacoesSenior($params);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
