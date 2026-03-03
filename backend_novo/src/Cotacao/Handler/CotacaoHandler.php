<?php

declare(strict_types=1);

namespace Cotacao\Handler;

use Cotacao\Service\CotacaoService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class CotacaoHandler implements RequestHandlerInterface
{
    private CotacaoService $service;
    private PDO $pdo;

    public function __construct(CotacaoService $service, PDO $pdo)
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
                'listar_itens' => $this->service->listarItensComDetalhes($params['instance_id'] ?? 0),
                'listar_cotacoes' => $this->service->buscarCotacoesDoItem($params),
                'salvar_lote' => $this->atomic(fn() => $this->service->salvarLote($params)),
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
