<?php
namespace App\Modulos\OrdemDeCompra;

use App\Core\BaseService;
use App\Modulos\OrdemDeCompra\Integracao\OrdemCompraSeniorClient;
use Exception;

class OrdemDeCompraService extends BaseService
{
    private OrdemDeCompraRepo $repo;
    private OrdemCompraSeniorClient $integracao;

    public function __construct(OrdemDeCompraRepo $repo, OrdemCompraSeniorClient $integracao)
    {
        $this->repo = $repo;
        $this->integracao = $integracao;
    }

    public function listarAutorizacoes($idProcesso): array
    {
        return [
            'sucesso' => true,
            'autorizacoes' => array_map(function (array $linha) {
                $fornecedor = $this->repo->buscarDadosFornecedorSenior((int)$linha['id_fornecedor_senior']);
                $nomeFornecedor = $fornecedor['nomfor'] ?? $linha['nome_do_fornecedor'] ?? '';

                return [
                    'id_autorizacao' => (string)$linha['id_autorizacao'],
                    'id_fornecedor_senior' => (int)$linha['id_fornecedor_senior'],
                    'nome_do_fornecedor' => $nomeFornecedor ?: ('Fornecedor ' . $linha['id_fornecedor_senior']),
                    'valor_total_pedido' => (float)($linha['valor_total_pedido'] ?? 0),
                    'ordem_de_compra' => $linha['ordem_de_compra'] !== null ? (int)$linha['ordem_de_compra'] : null,
                ];
            }, $this->repo->listarAutorizacoes((int)$idProcesso)),
        ];
    }

    public function gerarOrdemCompra($idProcesso, $idAutorizacao, $idFornecedorSenior): array
    {
        $idProcesso = (int)$idProcesso;
        $idAutorizacao = trim((string)$idAutorizacao);
        $idFornecedorSenior = (int)$idFornecedorSenior;

        if ($idAutorizacao === '') {
            throw new Exception('ID da autorizacao obrigatorio.');
        }
        if ($idFornecedorSenior <= 0) {
            throw new Exception('Fornecedor Senior obrigatorio.');
        }

        $autorizacao = $this->repo->buscarAutorizacao($idProcesso, $idAutorizacao, $idFornecedorSenior);
        if (!$autorizacao) {
            throw new Exception('Autorizacao de compra nao encontrada para este processo e fornecedor.');
        }

        $itens = $this->repo->buscarItensAutorizacao($idProcesso, $idAutorizacao, $idFornecedorSenior);
        if (empty($itens)) {
            throw new Exception('Nenhum item encontrado para esta autorizacao.');
        }

        $retorno = $this->integracao->gravarOrdemCompra($autorizacao, $itens);

        if (!$retorno['sucesso']) {
            return [
                'sucesso' => false,
                'mensagem' => $retorno['mensagem'] ?: 'Erro ao gerar ordem de compra no Senior.',
                'xml_envio' => $retorno['xml_envio'],
                'xml_retorno' => $retorno['xml_retorno'],
            ];
        }

        $this->repo->atualizarNumeroOrdemCompra(
            $idProcesso,
            $idAutorizacao,
            $idFornecedorSenior,
            $retorno['num_ocp']
        );

        return [
            'sucesso' => true,
            'mensagem' => 'Ordem de compra gerada com sucesso.',
            'num_ocp' => $retorno['num_ocp'],
            'xml_envio' => $retorno['xml_envio'],
            'xml_retorno' => $retorno['xml_retorno'],
        ];
    }
}
