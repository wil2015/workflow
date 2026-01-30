<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';
require_once __DIR__ . '/../../core/Documentos/Engine/DocumentoEngine.php';
require_once __DIR__ . '/Documentos/AutorizacaoDoc.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

class AutorizacaoCompraService extends BaseService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        parent::__construct($pdo, $connSenior);
        $this->repo = new AutorizacaoCompraRepo($pdo, $connSenior);
    }

    // =========================================================================
    //  FASE 1: GERAR PDF (Apenas gera e salva. SEM E-MAIL)
    // =========================================================================
    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        
        // Busca Itens
        $itensRaw = $this->repo->buscarItensVencedores($idProcesso);

        // --- MENSAGEM DE ERRO ESPIÃ ---
        if (empty($itensRaw)) {
             // Esta mensagem vai aparecer na tela vermelha. Leia ela com atenção!
             throw new Exception("DEBUG: O sistema buscou itens para o ID '{$idProcesso}' mas o Banco retornou 0 registros. (Tabela vazia ou ID incorreto?)");
        }

        $agrupado = $this->agruparDadosVencedores($itensRaw);

        // Configura Engine (Dummy - Não envia email aqui)
        $dsn = 'smtp://null:null@localhost'; 
        $mailer = new Mailer(Transport::fromDsn($dsn));
        $engine = new DocumentoEngine($mailer);

        // Limpa anteriores
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        foreach ($agrupado as $fornecedor) {
            $doc = new AutorizacaoDoc([
                'id_autorizacao'  => $fornecedor['id_vencedor'],
                'numero_processo' => $idProcesso . '/2026',
                'fornecedor_nome' => $fornecedor['nome'],
                'fornecedor_cnpj' => $fornecedor['cnpj'],
                'itens'           => $fornecedor['itens']
            ]);
            
            try {
                // False = Não enviar e-mail
                $caminhoAbsoluto = $engine->processar($doc, $idProcesso, null, false);
                
                // Limpa caminho para o Banco (Storage/docs...)
                $caminhoRelativo = str_replace('/var/www/html/public/', '', $caminhoAbsoluto);
                $caminhoRelativo = ltrim($caminhoRelativo, '/');

                $meta = [
                    'caminho_relativo' => $caminhoRelativo,
                    'nome_arquivo'     => basename($caminhoAbsoluto),
                    'hash_sha256'      => file_exists($caminhoAbsoluto) ? hash_file('sha256', $caminhoAbsoluto) : ''
                ];
                
                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
                $logs[] = "Gerado: {$fornecedor['nome']}";

            } catch (Exception $e) {
                $logs[] = "Erro {$fornecedor['nome']}: " . $e->getMessage();
            }
        }

        return ['sucesso' => true, 'logs' => $logs];
    }

    // =========================================================================
    //  FASE 2: ENVIAR E-MAIL (Botão Separado)
    // =========================================================================
    public function enviarEmailsEConcluir($idProcesso, $idUsuario) {
        
        // SMTP Real
        $dsn = 'smtp://usuario:senha@smtp.mailtrap.io:2525'; 
        $mailer = new Mailer(Transport::fromDsn($dsn));

        $itensRaw = $this->repo->buscarItensVencedores($idProcesso);
        $agrupado = $this->agruparDadosVencedores($itensRaw);
        
        $logs = [];
        $enviados = 0;

        foreach ($agrupado as $fornecedor) {
            if (empty($fornecedor['email'])) {
                $logs[] = "Sem e-mail: {$fornecedor['nome']}";
                continue;
            }

            // Busca o PDF gerado na Fase 1
            $padraoNome = "auth_" . $fornecedor['id_vencedor'];
            $docBanco = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, $padraoNome);

            if (!$docBanco) {
                $logs[] = "Erro: PDF não gerado para {$fornecedor['nome']}. Gere novamente.";
                continue;
            }

            $caminhoAnexo = '/var/www/html/public/' . $docBanco['caminho_arquivo'];

            if (!file_exists($caminhoAnexo)) {
                $logs[] = "Erro: Arquivo não existe no disco.";
                continue;
            }

            try {
                $email = (new Email())
                    ->from('compras@unesp.br')
                    ->to($fornecedor['email'])
                    ->subject("Autorização de Compra - Proc. $idProcesso")
                    ->html("<p>Segue documento anexo.</p>")
                    ->attachFromPath($caminhoAnexo, 'Autorizacao.pdf');

                $mailer->send($email);
                $enviados++;
                $logs[] = "Enviado para {$fornecedor['nome']}";

            } catch (Exception $e) {
                $logs[] = "Falha envio: " . $e->getMessage();
            }
        }

        if ($enviados == 0 && count($agrupado) > 0) {
             return ['sucesso' => false, 'erro' => 'Nenhum e-mail enviado. Verifique SMTP.', 'logs' => $logs];
        }

        return ['sucesso' => true, 'mensagem' => "$enviados e-mails enviados.", 'logs' => $logs];
    }

    private function agruparDadosVencedores($itensRaw) {
        $agrupado = [];
        foreach ($itensRaw as $row) {
            $codForn = $row['id_fornecedor_senior'];
            if (!isset($agrupado[$codForn])) {
                $dadosSenior = $this->repo->buscarDadosFornecedorSenior($codForn);
                $agrupado[$codForn] = [
                    'id_vencedor' => $codForn,
                    'nome'        => $dadosSenior['nomfor'] ?? "Forn. $codForn",
                    'cnpj'        => $dadosSenior['cgccpf'] ?? '',
                    'email'       => $dadosSenior['intnet'] ?? null,
                    'itens'       => []
                ];
            }
            $detalhe = $this->repo->buscarDetalheItemSenior($row['num_solicitacao'], $row['seq_solicitacao']);
            $agrupado[$codForn]['itens'][] = [
                'descricao'  => $detalhe['cplpro'] ?? 'Item',
                'quantidade' => $row['quantidade'],
                'valor'      => $row['valor_cotado'],
                'valor_total'=> $row['quantidade'] * $row['valor_cotado']
            ];
        }
        return $agrupado;
    }

    public function listarDocumentosGerados($idProcesso) {
        return $this->repo->listarDocumentosPorProcesso($idProcesso);
    }
}