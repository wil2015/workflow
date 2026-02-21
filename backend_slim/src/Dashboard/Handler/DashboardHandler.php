<?php

declare(strict_types=1);

namespace Dashboard\Handler;

use App\Handler\HandlerHelper;
use Dashboard\Service\DashboardService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class DashboardHandler
{
    use HandlerHelper;

    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $acao = $params['acao'] ?? 'home';

            $result = match ($acao) {
                'home' => $this->service->carregarHome(),
                default => throw new \Exception("Acao invalida: $acao"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
