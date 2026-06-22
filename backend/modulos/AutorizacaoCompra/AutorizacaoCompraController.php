<?php
namespace App\Modulos\AutorizacaoCompra;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;
use Throwable;

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        // DI Manual
        $repo = new AutorizacaoCompraRepo($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($repo);
    }

    protected function executarAcao(string $acao)
    {
        $idProcesso = $this->getParam('instance_id', 0, 'int');
        $idUsuario  = $this->getParam('id_usuario', 1, 'int');

        if ($idProcesso === 0) throw new Exception("ID do processo obrigatório.");

        switch ($acao) {
            // Novo fluxo: monta o HTML pelo Twig e devolve para edição no Tiptap.
            // Mantive gerar_autorizacoes como alias para não quebrar chamadas antigas,
            // mas ele não gera mais o PDF diretamente.
            case 'preparar_autorizacoes':
            case 'gerar_autorizacoes':
                $idAutorizacao = $this->getParam('id_autorizacao', 0, 'int');
                return $this->service->prepararDocumentosParaEdicao($idProcesso, $idUsuario, $idAutorizacao);

            // Segundo passo: recebe o HTML editado no Tiptap e só então emite o PDF.
            case 'emitir_autorizacao_editada':
                $idAutorizacao = $this->getParam('id_autorizacao', 0, 'int');
                $htmlDocumento = $this->getParam('html_documento', '', 'string');

                if ($idAutorizacao === 0) throw new Exception("ID da autorização obrigatório.");
                if (trim($htmlDocumento) === '') throw new Exception("HTML do documento obrigatório.");

                return $this->atomic(fn() =>
                    $this->service->emitirDocumentoEditado(
                        $idProcesso,
                        $idUsuario,
                        $idAutorizacao,
                        $htmlDocumento
                    )
                );

            case 'enviar_emails':
                return $this->atomic(fn() => $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario));
            case 'listar_documentos':
                return $this->service->listarDocumentosGerados($idProcesso);
            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    $controller = new AutorizacaoCompraController($pdo, $senior);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}
