<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Handler;

use AutorizacaoCompra\Service\AutorizacaoCompraService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class AutorizacaoCompraHandler implements RequestHandlerInterface
{
    private AutorizacaoCompraService $service;
    private PDO $pdo;

    public function __construct(AutorizacaoCompraService $service, PDO $pdo)
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
        $idProcesso = (int)($params['instance_id'] ?? 0);
        $idUsuario = (int)($params['id_usuario'] ?? 1);

        try {
            if ($idProcesso === 0) throw new \Exception('ID do processo obrigatorio.');

            $result = match ($acao) {
                'gerar_autorizacoes' => $this->service->gerarDocumentosOficiais($idProcesso, $idUsuario),
                'enviar_emails' => $this->atomic(fn() => $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario)),
                'listar_documentos' => $this->service->listarDocumentosGerados($idProcesso),
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
