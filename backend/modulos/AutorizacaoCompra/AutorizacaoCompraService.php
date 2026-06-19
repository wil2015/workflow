<?php
namespace App\Modulos\AutorizacaoCompra;

use App\Core\BaseService;
use App\Core\Documentos\Engine\DocumentoEngine;
use App\Modulos\AutorizacaoCompra\Documentos\AutorizacaoDoc;
use App\Modulos\AutorizacaoCompra\Documentos\AutorizacaoHtmlEditadoDoc;
use App\Modulos\AutorizacaoCompra\Dto\AutorizacaoDTO;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Exception;

class AutorizacaoCompraService extends BaseService
{
    private $repo;

    public function __construct(AutorizacaoCompraRepo $repo) {
        $this->repo = $repo;
    }

    public function listarDocumentosGerados($idProcesso) {
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        foreach ($docs as &$doc) {
            $pathLimpo = ltrim(str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? ''), '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);
        }

        return $docs;
    }

    /**
     * Novo passo 1:
     * Gera o snapshot e renderiza o Twig como HTML inicial,
     * mas NÃO chama o DocumentoEngine para criar PDF.
     */
    public function prepararDocumentosParaEdicao($idProcesso, $idUsuario) {
        $idProcesso = (int)$idProcesso;
        $this->repo->executarSnapshotDados($idProcesso, $idUsuario);

        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        if (empty($autorizacoes)) throw new Exception("Nenhuma autorização gerada.");

        $documentos = [];

        foreach ($autorizacoes as $auth) {
            $dto = $this->montarDtoAutorizacao($idProcesso, $auth);
            $doc = new AutorizacaoDoc($dto);

            $htmlCompleto = $this->renderizarTwig($doc);
            $htmlEditavel = $this->extrairConteudoBody($htmlCompleto);

            $documentos[] = [
                'id_autorizacao' => (int)$auth['id'],
                'titulo' => 'Autorização #' . $auth['id'] . ' - ' . $dto->fornecedorNome,
                'html' => $htmlEditavel
            ];
        }

        return [
            'sucesso' => true,
            'documentos' => $documentos
        ];
    }

    /**
     * Novo passo 2:
     * Recebe o HTML editado no Tiptap e só então gera/registrar o PDF.
     */
    public function emitirDocumentoEditado($idProcesso, $idUsuario, $idAutorizacao, $htmlDocumento) {
        $idProcesso = (int)$idProcesso;
        $idAutorizacao = (int)$idAutorizacao;

        $auth = $this->repo->buscarAutorizacaoPorId($idProcesso, $idAutorizacao);
        if (!$auth) {
            throw new Exception("Autorização não encontrada para este processo.");
        }

        $htmlLimpo = $this->sanitizarHtmlBasico($htmlDocumento);

        $engine = new DocumentoEngine(new Mailer(Transport::fromDsn('smtp://null:null@localhost')));
        $doc = new AutorizacaoHtmlEditadoDoc(
            $idAutorizacao,
            "$idProcesso/" . date('Y'),
            $htmlLimpo
        );

        $pathAbs = $engine->processar($doc, $idProcesso);

        $pathRel = ltrim(str_replace('/var/www/html/', '', $pathAbs), '/');
        $meta = [
            'caminho_relativo' => $pathRel,
            'nome_arquivo' => basename($pathAbs),
            'hash_sha256' => file_exists($pathAbs) ? hash_file('sha256', $pathAbs) : ''
        ];

        // Só remove o registro anterior depois que o novo PDF foi gerado.
        $this->repo->limparDocumentoAutorizacaoAnterior($idProcesso, $idAutorizacao);
        $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);

        return [
            'sucesso' => true,
            'documento' => $meta,
            'logs' => ['Emitido: ' . $meta['nome_arquivo']]
        ];
    }

    /**
     * Fluxo antigo mantido como referência/compatibilidade.
     * O frontend novo não chama este método diretamente.
     */
    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        $idProcesso = (int)$idProcesso;
        $this->repo->executarSnapshotDados($idProcesso, $idUsuario);

        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        if (empty($autorizacoes)) throw new Exception("Nenhuma autorização gerada.");

        $engine = new DocumentoEngine(new Mailer(Transport::fromDsn('smtp://null:null@localhost')));
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        foreach ($autorizacoes as $auth) {
            try {
                $dto = $this->montarDtoAutorizacao($idProcesso, $auth);
                $doc = new AutorizacaoDoc($dto);
                $pathAbs = $engine->processar($doc, $idProcesso);

                $pathRel = ltrim(str_replace('/var/www/html/', '', $pathAbs), '/');
                $meta = [
                    'caminho_relativo' => $pathRel, 
                    'nome_arquivo' => basename($pathAbs),
                    'hash_sha256' => file_exists($pathAbs) ? hash_file('sha256', $pathAbs) : ''
                ];

                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
                $logs[] = "Gerado: " . $meta['nome_arquivo'];
            } catch (Exception $e) {
                $logs[] = "Erro: " . $e->getMessage();
            }
        }

        return ['sucesso' => true, 'logs' => $logs];
    }

    private function montarDtoAutorizacao($idProcesso, array $auth) {
        $itens = $this->repo->buscarItensDoSnapshot($auth['id']);
        $forn = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);

        $dto = new AutorizacaoDTO(
            (int)$auth['id'],
            "$idProcesso/" . date('Y'),
            $forn['nomfor'] ?? '',
            $forn['cgccpf'] ?? '',
            (float)$auth['valor_total_pedido']
        );

        foreach ($itens as $item) {
            $dto->addItem(
                $item['descricao_item_snapshot'],
                $item['quantidade'],
                $item['valor_unitario_congelado'],
                $item['valor_total_item']
            );
        }

        return $dto;
    }

    private function renderizarTwig(AutorizacaoDoc $doc) {
        $templatePath = $doc->getCaminhoTemplate();

        $loader = new FilesystemLoader(dirname($templatePath));
        $twig = new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html'
        ]);

        return $twig->render(basename($templatePath), $doc->getDados());
    }

    private function extrairConteudoBody($html) {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return trim($html);
    }

    /**
     * Sanitização inicial.
     * Recomendação: substituir por HTML Purifier em produção.
     */
    private function sanitizarHtmlBasico($html) {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html);
        $html = preg_replace('/javascript\s*:/is', '', $html);

        return trim($html);
    }

    public function enviarEmailsEConcluir($idProcesso, $idUsuario) {
        $transport = Transport::fromDsn('smtp://usuario:senha@smtp.mailtrap.io:2525');
        $mailer = new Mailer($transport);

        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        $logs = []; $enviados = 0;

        foreach ($autorizacoes as $auth) {
            $dados = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);
            if (empty($dados['intnet'])) {
                $logs[] = "Fornecedor {$dados['nomfor']} sem email.";
                continue;
            }

            $doc = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, "auth_" . $auth['id']);
            $pathFisico = '';
            if ($doc) {
                $relativo = ltrim(str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo']), '/');
                $pathFisico = '/var/www/html/' . $relativo;
            }

            if ($doc && file_exists($pathFisico)) {
                try {
                    $email = (new Email())
                        ->from('compras@unesp.br')
                        ->to($dados['intnet'])
                        ->subject("Autorização de Compra #$idProcesso")
                        ->html("<p>Prezado fornecedor, segue autorização anexa.</p>")
                        ->attachFromPath($pathFisico, 'Autorizacao_Compra.pdf');

                    $mailer->send($email);
                    $enviados++; 
                    $logs[] = "Enviado para: {$dados['intnet']}";
                } catch (Exception $e) {
                    $logs[] = "Falha email: " . $e->getMessage();
                }
            }
        }

        return ['sucesso' => $enviados > 0, 'logs' => $logs];
    }
}
