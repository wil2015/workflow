<?php

declare(strict_types=1);

namespace Fornecedores\Handler;

use App\Handler\HandlerHelper;
use Fornecedores\Service\FornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ListarFornecedoresHandler
{
    use HandlerHelper;

    private FornecedoresService $service;

    public function __construct(FornecedoresService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $result = $this->service->listarParaDatatable($params);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
