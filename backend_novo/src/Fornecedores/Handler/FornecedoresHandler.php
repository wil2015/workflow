<?php

declare(strict_types=1);

namespace Fornecedores\Handler;

use Fornecedores\Service\FornecedoresService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class FornecedoresHandler implements RequestHandlerInterface
{
    private FornecedoresService $service;
    private PDO $pdo;

    public function __construct(FornecedoresService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge(
            $request->getQueryParams(),
            (array)($request->getParsedBody() ?? [])
        );

        $acao = $params['acao'] ?? '';

        try {
            $result = match ($acao) {
                'listar' => $this->service->listarParaDatatable($params),
                'salvar_lote' => $this->atomic(fn() => $this->service->salvarLote($params)),
                'remover' => $this->atomic(fn() => $this->service->remover($params['id_processo'] ?? '', $params['cod_fornecedor'] ?? '')),
                default => throw new \Exception("Acao desconhecida: '$acao'"),
            };

            return new JsonResponse($result);
        } catch (Throwable $e) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'local' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 400);
        }
    }

    private function atomic(callable $fn)
    {
        if ($this->pdo->inTransaction()) return $fn();
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
