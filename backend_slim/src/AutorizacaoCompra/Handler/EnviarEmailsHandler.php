<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Handler;

use App\Handler\HandlerHelper;
use AutorizacaoCompra\Service\AutorizacaoCompraService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

final class EnviarEmailsHandler
{
    use HandlerHelper;

    private AutorizacaoCompraService $service;
    private PDO $pdo;

    public function __construct(AutorizacaoCompraService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $idProcesso = (int)($args['processo'] ?? 0);
            $idUsuario = (int)($params['id_usuario'] ?? 1);
            $result = $this->atomic($this->pdo, fn() => $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario));
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
