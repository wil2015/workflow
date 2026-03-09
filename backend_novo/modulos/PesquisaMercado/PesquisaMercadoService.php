<?php
namespace App\Modulos\PesquisaMercado;

use App\Core\BaseService;
use App\Modulos\PesquisaMercado\Handler\PesquisaMercadoHandler;
use App\Modulos\PesquisaMercado\Documentos\PesquisaMercadoDoc;
use App\Core\Documentos\Engine\DocumentoEngine;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class PesquisaMercadoService extends BaseService
{
    private $repo;
    private $handler;

    public function __construct($pdo)
    {
        parent::__construct($pdo);
        $this->repo = new PesquisaMercadoRepo($pdo);
        $this->handler = new PesquisaMercadoHandler();
    }

    public function gerarRelatorioPesquisa(int $idProcesso, int $idUsuario)
    {
        // 1. Busca dados brutos
        $dadosBrutos = $this->repo->buscarDadosComparativos($idProcesso);
        
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
        $path = $engine->processar($doc, $idProcesso);

        return [
            'sucesso' => true,
            'arquivo' => basename($path),
            'url' => str_replace('/var/www/html/', '', $path)
        ];
    }
    public function listarDocumentosGerados($idProcesso) {
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        
        // Verifica se o arquivo PDF realmente existe no servidor
        foreach ($docs as &$doc) {
            $pathLimpo = str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? '');
            $pathLimpo = ltrim($pathLimpo, '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);
        }
        
        // Retorna a lista junto com uma verificação se a grade tem vencedores
        // (Isso controla o bloqueio/desbloqueio do botão no Vue.js)
        $temCotacao = $this->repo->verificarSeExisteVencedor($idProcesso);
        
        return [
            'documentos' => $docs,
            'tem_cotacao' => $temCotacao
        ];
    }
}