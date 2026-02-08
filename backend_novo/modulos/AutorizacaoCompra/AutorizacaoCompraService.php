<?php
namespace App\Modulos\AutorizacaoCompra;

use App\Core\BaseService;
use App\Core\Documentos\Engine\DocumentoEngine;
use App\Modulos\AutorizacaoCompra\Documentos\AutorizacaoDoc;
use App\Modulos\AutorizacaoCompra\Dto\AutorizacaoDTO;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
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
                $itens = $this->repo->buscarItensDoSnapshot($auth['id']);
                $forn = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);

                // DTO
                $dto = new AutorizacaoDTO(
                    (int)$auth['id'],
                    "$idProcesso/" . date('Y'),
                    $forn['nomfor'] ?? '',
                    $forn['cgccpf'] ?? '',
                    (float)$auth['valor_total_pedido']
                );

                foreach($itens as $item) {
                    $dto->addItem($item['descricao_item_snapshot'], $item['quantidade'], $item['valor_unitario_congelado'], $item['valor_total_item']);
                }

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
            } catch (Exception $e) { $logs[] = "Erro: " . $e->getMessage(); }
        }
        return ['sucesso' => true, 'logs' => $logs];
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
                } catch (Exception $e) { $logs[] = "Falha email: " . $e->getMessage(); }
            }
        }
        return ['sucesso' => $enviados > 0, 'logs' => $logs];
    }
}