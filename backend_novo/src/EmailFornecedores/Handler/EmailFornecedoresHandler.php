<?php

declare(strict_types=1);

namespace EmailFornecedores\Handler;

use EmailFornecedores\Service\EmailFornecedoresService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class EmailFornecedoresHandler implements RequestHandlerInterface
{
    private EmailFornecedoresService $service;
    private PDO $pdo;

    public function __construct(EmailFornecedoresService $service, PDO $pdo)
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
                'listar' => $this->service->carregarEmails($params['instance_id'] ?? 0),
                'salvar' => $this->atomic(fn() => $this->service->salvarEmailsSelecionados($params['instance_id'] ?? 0, $params['emails_selecionados'] ?? [])),
                'add_email' => $this->service->adicionarEmailManual($params['id_fornecedor_senior'] ?? 0, $params['email'] ?? ''),
                default => throw new \Exception("Acao desconhecida: $acao"),
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
