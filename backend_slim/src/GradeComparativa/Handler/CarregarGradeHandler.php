<?php

declare(strict_types=1);

namespace GradeComparativa\Handler;

use App\Handler\HandlerHelper;
use GradeComparativa\Service\GradeComparativaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class CarregarGradeHandler
{
    use HandlerHelper;

    private GradeComparativaService $service;

    public function __construct(GradeComparativaService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $idProcesso = $args['processo'] ?? 0;
            $result = $this->service->montarGradeParaFront($idProcesso);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
