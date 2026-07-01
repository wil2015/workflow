<?php
namespace App\Modulos\AutorizacaoCompra\Dto;

use App\Core\Utils\Formatador;

class AutorizacaoDTO {
    public string $idAutorizacao;
    public string $numeroProcesso;
    public string $fornecedorNome;
    public string $fornecedorCnpj;
    public string $logradouro;
    public string $numero;
    public string $complemento;
    public string $bairro;
    public string $cep;
    public string $cidade;
    public string $estado;
    public string $telefone1;
    public string $telefone2;
    public string $telefone3;
    public string $email;
    public ?float $valorTotalPedido;
    public array $itens = [];

    public function __construct(string $id, string $proc, string $nome, string $cnpj, ?float $total, array $dadosFornecedor = []) {
        $this->idAutorizacao = $id;
        $this->numeroProcesso = $proc;
        $this->fornecedorNome = $nome ?: 'Consumidor';
        $this->fornecedorCnpj = Formatador::documento($cnpj); // Helper formata
        $this->valorTotalPedido = $total;
        $this->logradouro = self::textoFornecedor($dadosFornecedor, 'endfor');
        $this->numero = self::textoFornecedor($dadosFornecedor, 'nenfor');
        $this->complemento = self::textoFornecedor($dadosFornecedor, 'cplend');
        $this->bairro = self::textoFornecedor($dadosFornecedor, 'baifor');
        $this->cep = self::textoFornecedor($dadosFornecedor, 'cepfor');
        $this->cidade = self::textoFornecedor($dadosFornecedor, 'cidfor');
        $this->estado = self::textoFornecedor($dadosFornecedor, 'sigufs');
        $this->telefone1 = self::textoFornecedor($dadosFornecedor, 'fonfor');
        $this->telefone2 = self::textoFornecedor($dadosFornecedor, 'fonfo2');
        $this->telefone3 = self::textoFornecedor($dadosFornecedor, 'fonfo3');
        $this->email = self::textoFornecedor($dadosFornecedor, 'intnet');
    }

    public function addItem($desc, $qtd, $valUnit, $valTotal) {
        $this->itens[] = [
            'descricao' => $desc,
            'quantidade' => Formatador::numero((float)$qtd), 
            'valor' => Formatador::moeda((float)$valUnit),
            'valor_total' => Formatador::moeda((float)$valTotal)
        ];
    }

    private static function textoFornecedor(array $dadosFornecedor, string $campo): string {
        if (array_key_exists($campo, $dadosFornecedor)) {
            return trim((string)$dadosFornecedor[$campo]);
        }

        $campoNormalizado = strtolower($campo);
        foreach ($dadosFornecedor as $chave => $valor) {
            if (strtolower((string)$chave) === $campoNormalizado) {
                return trim((string)$valor);
            }
        }

        return '';
    }
}
