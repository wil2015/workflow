<?php

declare(strict_types=1);

namespace Fornecedores\Handler;

use App\Handler\HandlerHelper;
use Fornecedores\Service\FornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

final class RemoverFornecedorHandler
{
    use HandlerHelper;

    private FornecedoresService $service;
    private PDO $pdo;

    public function __construct(FornecedoresService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $idProcesso = $args['processo'] ?? '';
            $codFornecedor = $args['fornecedor'] ?? '';
            $result = $this->atomic($this->pdo, fn() => $this->service->remover($idProcesso, $codFornecedor));
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
