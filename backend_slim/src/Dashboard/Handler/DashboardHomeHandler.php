<?php

declare(strict_types=1);

namespace Dashboard\Handler;

use App\Handler\HandlerHelper;
use Dashboard\Service\DashboardService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class DashboardHomeHandler
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
            $result = $this->service->carregarHome();
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
