<?php

declare(strict_types=1);

namespace Cotacao\Handler;

use App\Handler\HandlerHelper;
use Cotacao\Service\CotacaoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ListarItensCotacaoHandler
{
    use HandlerHelper;

    private CotacaoService $service;

    public function __construct(CotacaoService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $idProcesso = $args['processo'] ?? 0;
            $result = $this->service->listarItensComDetalhes($idProcesso);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
