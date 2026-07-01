<?php
namespace App\Modulos\AutorizacaoCompra\Documentos;

use App\Core\Documentos\Interfaces\DocumentoInterface;
use App\Modulos\AutorizacaoCompra\Dto\AutorizacaoDTO;
use App\Core\Utils\Formatador;

class AutorizacaoDoc implements DocumentoInterface {
    private AutorizacaoDTO $dto;

    public function __construct(AutorizacaoDTO $dto) {
        $this->dto = $dto;
    }

    public function getAssunto() {
        return "Autorização de Compra - Processo " . $this->dto->numeroProcesso;
    }

    public function getCaminhoTemplate() {
        return __DIR__ . '/autorizacao.html.twig';
    }

    public function getDados() {
        return [
            'id_autorizacao' => $this->dto->idAutorizacao,
            'numero_autorizacao' => $this->numeroAutorizacao(),
            'numero_processo' => $this->dto->numeroProcesso,
            'data_emissao_extenso' => $this->dataEmissaoExtenso(),
            'fornecedor_nome' => $this->dto->fornecedorNome,
            'fornecedor_cnpj' => $this->dto->fornecedorCnpj,
            'logradouro' => $this->dto->logradouro,
            'numero' => $this->dto->numero,
            'complemento' => $this->dto->complemento,
            'bairro' => $this->dto->bairro,
            'cep' => $this->dto->cep,
            'cidade' => $this->dto->cidade,
            'estado' => $this->dto->estado,
            'telefone1' => $this->dto->telefone1,
            'telefone2' => $this->dto->telefone2,
            'telefone3' => $this->dto->telefone3,
            'email' => $this->dto->email,
            'itens' => $this->dto->itens,
            'valor_total_pedido' => Formatador::moeda($this->dto->valorTotalPedido)
        ];
    }

    public function getNomePastaStorage() { return 'autorizacao_compra'; }
    public function getNomeArquivoBase() { return 'auth_' . $this->normalizarIdArquivo($this->dto->idAutorizacao); }

    private function numeroAutorizacao(): string {
        $id = trim($this->dto->idAutorizacao);

        if (preg_match('/\/\d{4}$/', $id)) {
            return $id;
        }

        return $id . '/' . date('Y');
    }

    private function dataEmissaoExtenso(): string {
        $meses = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ];

        $dia = date('d');
        $mes = $meses[(int)date('n')];
        $ano = date('Y');

        return "São Paulo, {$dia} de {$mes} de {$ano}";
    }

    private function normalizarIdArquivo(string $idAutorizacao): string {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $idAutorizacao), '_');
    }
}
