<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use ExecucaoFluxo\Service\FluxoService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class FluxoHandler implements RequestHandlerInterface
{
    private FluxoService $service;
    private PDO $pdo;

    public function __construct(FluxoService $service, PDO $pdo)
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
        if (empty($acao)) {
            return new JsonResponse(['erro' => 'Nenhuma acao fornecida.'], 400);
        }

        try {
            $result = match ($acao) {
                'ler_tarefa' => $this->service->carregarPassoAtual($params['id_instancia'] ?? null),
                'salvar_datas' => $this->atomic(fn() => $this->service->salvarDatasPrevisao($params)),
                'vincular' => $this->atomic(fn() => $this->service->vincularItens($params)),
                'listar_solicitacoes' => $this->service->listarSolicitacoesSenior($params),
                'remover_item' => $this->atomic(fn() => $this->service->removerItem($params['id'] ?? 0, $params['num'] ?? 0, $params['seq'] ?? 0)),
                'cancelar_processo' => $this->atomic(fn() => $this->service->cancelarProcesso($params['id'] ?? 0)),
                'dashboard_data' => $this->service->carregarDadosDashboard(),
                default => throw new \Exception("Acao desconhecida: " . $acao),
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
        if ($this->pdo->inTransaction()) {
            return $fn();
        }

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
