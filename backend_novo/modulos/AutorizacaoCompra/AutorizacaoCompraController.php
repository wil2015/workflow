<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/AutorizacaoCompraService.php';

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        // --- A CAPTURA TOTAL DO ID ---
        $idProcesso = $this->params['instance_id']      // JSON do Vue
                      ?? $_GET['instance_id']           // URL (?instance_id=16)
                      ?? $this->params['id_processo'] 
                      ?? $_GET['id_processo']
                      ?? $this->params['id']
                      ?? 0;

        // Se o ID for 0, forçamos um erro visível
        if (in_array($acao, ['gerar_autorizacoes', 'enviar_email_concluir']) && empty($idProcesso)) {
             throw new Exception("ERRO DE CONEXÃO: O ID do processo chegou como '0' ou vazio. Verifique a URL.");
        }

        switch ($acao) {
            case 'gerar_autorizacoes':
                return $this->atomic(function() use ($idProcesso) {
                    return $this->service->gerarDocumentosOficiais(
                        $idProcesso,
                        $this->params['id_usuario'] ?? 1
                    );
                });

            case 'enviar_email_concluir':
                return $this->atomic(function() use ($idProcesso) {
                    return $this->service->enviarEmailsEConcluir(
                        $idProcesso,
                        $this->params['id_usuario'] ?? 1
                    );
                });

            case 'listar_documentos':
                return $this->service->listarDocumentosGerados($idProcesso);

            default:
                throw new Exception("Ação desconhecida: $acao");
        }
    }
}