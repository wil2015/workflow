<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Handler;

use App\Handler\HandlerHelper;
use AutorizacaoCompra\Service\AutorizacaoCompraService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class GerarAutorizacoesHandler
{
    use HandlerHelper;

    private AutorizacaoCompraService $service;

    public function __construct(AutorizacaoCompraService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $idProcesso = (int)($args['processo'] ?? 0);
            $idUsuario = (int)($params['id_usuario'] ?? 1);
            $result = $this->service->gerarDocumentosOficiais($idProcesso, $idUsuario);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
