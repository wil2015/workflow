<?php

declare(strict_types=1);

namespace Fornecedores\Handler;

use App\Handler\HandlerHelper;
use Fornecedores\Service\FornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

class FornecedoresHandler
{
    use HandlerHelper;

    private FornecedoresService $service;
    private PDO $pdo;

    public function __construct(FornecedoresService $service, PDO $pdo)
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
                'listar' => $this->service->listarParaDatatable($params),
                'salvar_lote' => $this->atomic($this->pdo, fn() => $this->service->salvarLote($params)),
                'remover' => $this->atomic($this->pdo, fn() => $this->service->remover($params['id_processo'] ?? '', $params['cod_fornecedor'] ?? '')),
                default => throw new \Exception("Acao desconhecida: '$acao'"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
