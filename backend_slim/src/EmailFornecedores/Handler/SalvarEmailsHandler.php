<?php

declare(strict_types=1);

namespace EmailFornecedores\Handler;

use App\Handler\HandlerHelper;
use EmailFornecedores\Service\EmailFornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

final class SalvarEmailsHandler
{
    use HandlerHelper;

    private EmailFornecedoresService $service;
    private PDO $pdo;

    public function __construct(EmailFornecedoresService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $idProcesso = $args['processo'] ?? 0;
            $result = $this->atomic($this->pdo, fn() => $this->service->salvarEmailsSelecionados(
                $idProcesso,
                $params['emails_selecionados'] ?? []
            ));
            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
