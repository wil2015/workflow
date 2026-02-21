<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use App\Handler\HandlerHelper;
use ExecucaoFluxo\Service\FluxoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

class FluxoHandler
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
            $acao = $params['acao'] ?? '';

            if (empty($acao)) {
                return $this->errorResponse("Nenhuma acao fornecida.");
            }

            $result = match ($acao) {
                'ler_tarefa' => $this->service->carregarPassoAtual($params['id_instancia'] ?? null),
                'salvar_datas' => $this->atomic($this->pdo, fn() => $this->service->salvarDatasPrevisao($params)),
                'vincular' => $this->atomic($this->pdo, fn() => $this->service->vincularItens($params)),
                'listar_solicitacoes' => $this->service->listarSolicitacoesSenior($params),
                'remover_item' => $this->atomic($this->pdo, fn() => $this->service->removerItem($params['id'] ?? 0, $params['num'] ?? 0, $params['seq'] ?? 0)),
                'cancelar_processo' => $this->atomic($this->pdo, fn() => $this->service->cancelarProcesso($params['id'] ?? 0)),
                'dashboard_data' => $this->service->carregarDadosDashboard(),
                default => throw new \Exception("Acao desconhecida: $acao"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
