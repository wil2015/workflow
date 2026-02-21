<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use App\Handler\HandlerHelper;
use ExecucaoFluxo\Service\FluxoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

final class SalvarDatasHandler
{
    use HandlerHelper;

    private FluxoService $service;
    private PDO $pdo;

    public function __construct(FluxoService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $result = $this->atomic($this->pdo, fn() => $this->service->salvarDatasPrevisao($params));
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
