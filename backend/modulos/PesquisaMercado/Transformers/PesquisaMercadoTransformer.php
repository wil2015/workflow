<?php
namespace App\Modulos\PesquisaMercado\Transformers;

class PesquisaMercadoTransformer
{
    public function formatarParaTabela(array $dadosBrutos): array
    {
        $itensAgrupados = [];

        foreach ($dadosBrutos as $linha) {
            $chave = $linha['num_solicitacao'] . '_' . $linha['seq_solicitacao'];
            
            if (!isset($itensAgrupados[$chave])) {
                $itensAgrupados[$chave] = [
                    'descricao' => $linha['descricao'],
                    'fornecedores' => []
                ];
            }

            $itensAgrupados[$chave]['fornecedores'][] = [
                'nome' => $linha['nome_do_fornecedor'],
                'documento' => $linha['cnpj_cpf'],
                'valor_unitario' => $linha['valor_unitario'] ?? 0,
                'valor_total' => $linha['valor_total_item'] ?? 0
            ];
        }

        return $itensAgrupados;
    }
}