<?php

declare(strict_types=1);

namespace EmailFornecedores\Handler;

use App\Handler\HandlerHelper;
use EmailFornecedores\Service\EmailFornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class CarregarEmailsHandler
{
    use HandlerHelper;

    private EmailFornecedoresService $service;

    public function __construct(EmailFornecedoresService $service)
    {
        $this->service = $service;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $idProcesso = $args['processo'] ?? 0;
            $result = $this->service->carregarEmails($idProcesso);
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
