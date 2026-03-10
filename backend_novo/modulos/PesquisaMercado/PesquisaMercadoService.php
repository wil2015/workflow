<?php
namespace App\Modulos\PesquisaMercado;

use App\Core\BaseService;
use App\Modulos\PesquisaMercado\Handler\PesquisaMercadoHandler;
use App\Modulos\PesquisaMercado\Documentos\PesquisaMercadoDoc;
use App\Core\Documentos\Engine\DocumentoEngine;
use App\Core\Utils\Formatador; // Usa o mesmo formatador do módulo de Cotação
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class PesquisaMercadoService extends BaseService
{
    private $repo;
    private $handler;

    // RECEBE A CONEXÃO SENIOR AQUI
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        // Passa o $connSenior para o repositório funcionar
        $this->repo = new PesquisaMercadoRepo($pdo, $connSenior);
        $this->handler = new PesquisaMercadoHandler();
    }

    public function gerarRelatorioPesquisa(int $idProcesso, int $idUsuario)
    {
        // 1. Busca dados brutos (MySQL local)
        $dadosBrutos = $this->repo->buscarDadosComparativos($idProcesso);
        
        // 1.5. Busca as descrições em tempo real no Senior
        foreach ($dadosBrutos as &$linha) {
            $detalhe = $this->repo->buscarDetalheSenior($linha['num_solicitacao'], $linha['seq_solicitacao']);
            if ($detalhe) {
                // Usa Formatador::utf8 ou mb_convert_encoding dependendo do seu helper
                $linha['descricao'] = mb_convert_encoding(trim($detalhe['cplpro']), 'UTF-8', 'ISO-8859-1'); 
            } else {
                $linha['descricao'] = "Item não encontrado no ERP Senior";
            }
        }
        
        // 2. Handler processa a lógica da tabela
        $itensFormatados = $this->handler->formatarParaTabela($dadosBrutos);

        // 3. Monta o Objeto de Documento
        $doc = new PesquisaMercadoDoc([
            'id_processo' => $idProcesso,
            'data_emissao' => date('d/m/Y'),
            'itens' => $itensFormatados
        ]);

        // 4. Engine gera o arquivo físico
        $engine = new DocumentoEngine(new Mailer(Transport::fromDsn('smtp://null:null@localhost')));
        $pathAbsoluto = $engine->processar($doc, $idProcesso);

        // --- 5. REGISTO NO BANCO DE DADOS (CÓDIGO NOVO) ---
        // Limpa pesquisas antigas deste processo para não acumular lixo
        $this->repo->limparDocumentosAnteriores($idProcesso, 'PESQUISA_MERCADO');

        // Prepara os metadados do arquivo
        $pathRelativo = ltrim(str_replace('/var/www/html/', '', $pathAbsoluto), '/');
        $meta = [
            'caminho_relativo' => $pathRelativo, 
            'nome_arquivo' => basename($pathAbsoluto),
            'hash_sha256' => file_exists($pathAbsoluto) ? hash_file('sha256', $pathAbsoluto) : ''
        ];
        
        // Salva na tabela documentos_oficiais
        $this->repo->registrarDocumento($idProcesso, 'PESQUISA_MERCADO', $meta, $idUsuario);

        return [
            'sucesso' => true,
            'arquivo' => $meta['nome_arquivo'],
            'url' => $meta['caminho_relativo']
        ];
    }
    
    public function listarDocumentosGerados($idProcesso) {
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        
        foreach ($docs as &$doc) {
            $pathLimpo = str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? '');
            $pathLimpo = ltrim($pathLimpo, '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);
        }
        
        $temCotacao = $this->repo->verificarSeExisteVencedor($idProcesso);
        
        return [
            'documentos' => $docs,
            'tem_cotacao' => $temCotacao
        ];
    }
}