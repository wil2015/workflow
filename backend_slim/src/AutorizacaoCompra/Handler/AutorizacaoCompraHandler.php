<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Handler;

use App\Handler\HandlerHelper;
use AutorizacaoCompra\Service\AutorizacaoCompraService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;
use Throwable;
use Exception;

class AutorizacaoCompraHandler
{
    use HandlerHelper;

    private AutorizacaoCompraService $service;
    private PDO $pdo;

    public function __construct(AutorizacaoCompraService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $this->getAllParams($request);
            $acao = $params['acao'] ?? '';
            $idProcesso = (int)($params['instance_id'] ?? 0);
            $idUsuario = (int)($params['id_usuario'] ?? 1);

            if ($idProcesso === 0) throw new Exception("ID do processo obrigatorio.");
            if (empty($acao)) return $this->errorResponse("Nenhuma acao fornecida.");

            $result = match ($acao) {
                'gerar_autorizacoes' => $this->service->gerarDocumentosOficiais($idProcesso, $idUsuario),
                'enviar_emails' => $this->atomic($this->pdo, fn() => $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario)),
                'listar_documentos' => $this->service->listarDocumentosGerados($idProcesso),
                default => throw new Exception("Acao desconhecida: '$acao'"),
            };

            return $this->jsonResponse($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
