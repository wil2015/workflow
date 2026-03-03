<?php

declare(strict_types=1);

namespace GradeComparativa\Handler;

use GradeComparativa\Service\GradeComparativaService;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class GradeComparativaHandler implements RequestHandlerInterface
{
    private GradeComparativaService $service;
    private PDO $pdo;

    public function __construct(GradeComparativaService $service, PDO $pdo)
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
                'carregar_grade' => $this->service->montarGradeParaFront($params['instance_id'] ?? 0),
                'consolidar_vencedores' => $this->service->consolidarProcesso($params['id_processo'] ?? 0, $params['ofertas'] ?? []),
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
}
