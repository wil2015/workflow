<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;
use Throwable;
use PDO;

trait HandlerHelper
{
    /**
     * Extrai todos os parametros unificados (query + body + parsed)
     */
    protected function getAllParams(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $body = $request->getParsedBody() ?? [];
        return array_merge($query, is_array($body) ? $body : []);
    }

    /**
     * Retorna JSON response padrao
     */
    protected function jsonResponse(mixed $data, int $status = 200): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * Retorna JSON de erro
     */
    protected function errorResponse(string $message, int $status = 400): ResponseInterface
    {
        return $this->jsonResponse([
            'sucesso' => false,
            'erro' => $message,
        ], $status);
    }

    /**
     * Executa callback dentro de transacao atomica
     */
    protected function atomic(PDO $pdo, callable $fn): mixed
    {
        if ($pdo->inTransaction()) {
            return $fn();
        }

        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
