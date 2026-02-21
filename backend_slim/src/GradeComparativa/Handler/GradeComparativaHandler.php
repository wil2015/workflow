<?php

declare(strict_types=1);

namespace GradeComparativa\Handler;

use App\Handler\HandlerHelper;
use GradeComparativa\Service\GradeComparativaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;

class GradeComparativaHandler
{
    use HandlerHelper;

    private GradeComparativaService $service;
    private PDO $pdo;

    public function __construct(GradeComparativaService $service, PDO $pdo)
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
                'carregar_grade' => $this->service->montarGradeParaFront($params['instance_id'] ?? 0),
                'consolidar_vencedores' => $this->service->consolidarProcesso($params['id_processo'] ?? 0, $params['ofertas'] ?? []),
                default => throw new \Exception("Acao desconhecida: '$acao'"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
