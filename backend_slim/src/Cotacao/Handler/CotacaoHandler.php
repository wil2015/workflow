<?php

declare(strict_types=1);

namespace Cotacao\Handler;

use App\Handler\HandlerHelper;
use Cotacao\Service\CotacaoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

class CotacaoHandler
{
    use HandlerHelper;

    private CotacaoService $service;
    private PDO $pdo;

    public function __construct(CotacaoService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $acao = $params['acao'] ?? '';

            if (empty($acao)) return $this->errorResponse("Nenhuma acao fornecida.");

            $result = match ($acao) {
                'listar_itens' => $this->service->listarItensComDetalhes($params['instance_id'] ?? 0),
                'listar_cotacoes' => $this->service->buscarCotacoesDoItem($params),
                'salvar_lote' => $this->atomic($this->pdo, fn() => $this->service->salvarLote($params)),
                default => throw new \Exception("Acao desconhecida: '$acao'"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
