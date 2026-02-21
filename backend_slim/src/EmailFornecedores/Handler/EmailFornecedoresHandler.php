<?php

declare(strict_types=1);

namespace EmailFornecedores\Handler;

use App\Handler\HandlerHelper;
use EmailFornecedores\Service\EmailFornecedoresService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

class EmailFornecedoresHandler
{
    use HandlerHelper;

    private EmailFornecedoresService $service;
    private PDO $pdo;

    public function __construct(EmailFornecedoresService $service, PDO $pdo)
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
                'listar' => $this->service->carregarEmails($params['instance_id'] ?? 0),
                'salvar' => $this->atomic($this->pdo, fn() => $this->service->salvarEmailsSelecionados($params['instance_id'] ?? 0, $params['emails_selecionados'] ?? [])),
                'add_email' => $this->service->adicionarEmailManual($params['id_fornecedor_senior'] ?? 0, $params['email'] ?? ''),
                default => throw new \Exception("Acao desconhecida: $acao"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
