<?php

declare(strict_types=1);

namespace GradeComparativa\Handler;

use App\Handler\HandlerHelper;
use GradeComparativa\Service\GradeComparativaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ConsolidarGradeHandler
{
    use HandlerHelper;

    private GradeComparativaService $service;

    public function __construct(GradeComparativaService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $result = $this->service->consolidarProcesso(
                $params['id_processo'] ?? 0,
                $params['ofertas'] ?? []
            );
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
