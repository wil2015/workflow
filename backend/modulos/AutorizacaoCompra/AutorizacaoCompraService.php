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

        // IDs das autorizacoes que existem HOJE no snapshot do processo.
        // Esses IDs devem ser a fonte de verdade para o botao Revisar / Emitir.
        $autorizacoesAtuais = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        $idsAtuais = array_map(fn($linha) => (int)$linha['id'], $autorizacoesAtuais);
        $idsAtuaisMap = array_flip($idsAtuais);

        $documentosPorAutorizacao = [];

        foreach ($docs as &$doc) {
            $pathLimpo = ltrim(str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? ''), '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);

            // Exemplo de arquivo: auth_42_20260618193416.pdf -> id_autorizacao = 42
            $fonteNome = ($doc['nome_arquivo'] ?? '') . ' ' . ($doc['caminho_arquivo'] ?? '');
            $idDocAutorizacao = 0;
            if (preg_match('/auth_(\d+)/i', $fonteNome, $m)) {
                $idDocAutorizacao = (int)$m[1];
            }

            $doc['id_autorizacao'] = $idDocAutorizacao ?: null;
            $doc['id_autorizacao_valido'] = $idDocAutorizacao > 0 && isset($idsAtuaisMap[$idDocAutorizacao]);

            // Como a consulta dos documentos vem em ordem decrescente de criacao,
            // o primeiro documento encontrado por autorizacao tende a ser o mais recente.
            if ($doc['id_autorizacao_valido'] && !isset($documentosPorAutorizacao[$idDocAutorizacao])) {
                $documentosPorAutorizacao[$idDocAutorizacao] = $doc;
            }
        }
        unset($doc);

        $autorizacoesResumo = [];
        foreach ($autorizacoesAtuais as $auth) {
            $idAutorizacao = (int)$auth['id'];
            $idFornecedorSenior = $this->obterIdFornecedorSenior($auth);
            $forn = $this->repo->buscarDadosFornecedorSenior($idFornecedorSenior);
            $docAtual = $documentosPorAutorizacao[$idAutorizacao] ?? null;

            $autorizacoesResumo[] = [
                'id_autorizacao' => $idAutorizacao,
                'id_fornecedor' => $idFornecedorSenior,
                'id_fornecedor_senior' => $idFornecedorSenior,
                'fornecedor_nome' => $forn['nomfor'] ?? ('Fornecedor ' . $idFornecedorSenior),
                'fornecedor_cnpj' => $forn['cgccpf'] ?? '',
                'valor_total_pedido' => (float)($auth['valor_total_pedido'] ?? 0),
                'documento' => $docAtual,
            ];
        }

        return [
            'sucesso' => true,
            'documentos' => $docs,
            'autorizacoes' => $autorizacoesResumo,
            // Mantem a tela operacional mesmo quando so ha PDFs antigos.
            // Nesse caso o front mostra os PDFs como historico e oferece preparar novamente.
            'tem_cotacao' => !empty($autorizacoesResumo) || !empty($docs),
        ];
    }

    /**
     * Novo passo 1:
     * Gera o snapshot e renderiza o Twig como HTML inicial,
     * mas NÃO chama o DocumentoEngine para criar PDF.
     */
    public function prepararDocumentosParaEdicao($idProcesso, $idUsuario, $idAutorizacao = 0) {
        $idProcesso = (int)$idProcesso;
        $idAutorizacao = (int)$idAutorizacao;

        if ($idAutorizacao > 0) {
            // IMPORTANTE:
            // Ao revisar uma autorizacao ja listada, nao execute a procedure novamente.
            // A procedure pode recriar o snapshot e alterar os IDs; isso faz o PDF auth_42
            // apontar para uma autorizacao que acabou de ser apagada/recriada.
            $auth = $this->repo->buscarAutorizacaoPorId($idProcesso, $idAutorizacao);
            if (!$auth) {
                $idsAtuais = array_map(
                    fn($linha) => (int)$linha['id'],
                    $this->repo->buscarAutorizacoesGeradas($idProcesso)
                );
                $detalhe = empty($idsAtuais) ? 'Nenhuma autorizacao atual encontrada.' : 'Autorizacoes atuais: ' . implode(', ', $idsAtuais) . '.';
                throw new Exception("Autorizacao #{$idAutorizacao} nao encontrada para este processo. {$detalhe}");
            }
            $autorizacoes = [$auth];
        } else {
            // Sem uma autorizacao especifica, estamos preparando/atualizando o conjunto do processo.
            $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
            if (empty($autorizacoes)) {
                $this->repo->executarSnapshotDados($idProcesso, $idUsuario);
                $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
            }
        }

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
        $idFornecedorSenior = $this->obterIdFornecedorSenior($auth);
        $itens = $this->repo->buscarItensDoSnapshot($idProcesso, $idFornecedorSenior);
        $forn = $this->repo->buscarDadosFornecedorSenior($idFornecedorSenior);

        $dto = new AutorizacaoDTO(
            (int)$auth['id'],
            "$idProcesso/" . date('Y'),
            $forn['nomfor'] ?? '',
            $forn['cgccpf'] ?? '',
            (float)$auth['valor_total_pedido'],
            $forn
        );

        foreach ($itens as $item) {
            $dto->addItem(
                $item['descricao_item_snapshot'],
                $item['quantidade'],
                $item['valor_cotado'] ?? $item['valor_unitario_congelado'] ?? 0,
                $item['valor_total'] ?? $item['valor_total_item'] ?? 0
            );
        }

        return $dto;
    }

    private function obterIdFornecedorSenior(array $auth): int {
        return (int)($auth['id_fornecedor_senior'] ?? $auth['id_fornecedor'] ?? 0);
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
            $idFornecedorSenior = $this->obterIdFornecedorSenior($auth);
            $dados = $this->repo->buscarDadosFornecedorSenior($idFornecedorSenior);
            if (empty($dados['intnet'])) {
                $nomeFornecedor = $dados['nomfor'] ?? ('Fornecedor ' . $idFornecedorSenior);
                $logs[] = "Fornecedor {$nomeFornecedor} sem email.";
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
