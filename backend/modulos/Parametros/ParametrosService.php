<?php
namespace App\Modulos\Parametros;

use App\Core\BaseService;
use App\Core\Utils\Formatador;
use DateTime;
use Exception;

class ParametrosService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior = null)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new ParametrosRepo($pdo, $connSenior);
    }

    public function carregar($idProcesso)
    {
        if (!$idProcesso) throw new Exception("ID obrigatorio.");

        $parametros = $this->repo->buscarPorProcesso($idProcesso);
        if (!$parametros) throw new Exception("Processo #$idProcesso nao encontrado.");

        return [
            'sucesso' => true,
            'parametros' => [
                'id_processo' => $parametros['id'],
                'data_cotacao' => $parametros['data_esperada_da_cotacao'] ?? '',
                'data_recebimento' => $parametros['data_esperada_do_recebimento'] ?? '',
                'endereco_da_entrega' => Formatador::utf8($parametros['endereco_da_entrega'] ?? '')
            ]
        ];
    }

    public function salvar($dados)
    {
        $id = $dados['id_processo'] ?? null;
        $dtCot = $this->normalizarData($dados['data_cotacao'] ?? null, 'Cotacao');
        $dtRec = $this->normalizarData($dados['data_recebimento'] ?? null, 'Entrega');
        $enderecoEntrega = trim((string)($dados['endereco_da_entrega'] ?? ''));
        if ($enderecoEntrega === '') $enderecoEntrega = null;

        if (!$id) throw new Exception("ID obrigatorio.");

        $hoje = date('Y-m-d');
        if ($dtCot && $dtCot < $hoje) throw new Exception("Cotacao menor que hoje.");
        if ($dtRec && $dtRec < $hoje) throw new Exception("Entrega menor que hoje.");
        if ($dtCot && $dtRec && $dtRec < $dtCot) throw new Exception("Entrega menor que Cotacao.");

        $this->repo->atualizar($id, $dtCot, $dtRec, $enderecoEntrega);

        return ['sucesso' => true, 'msg' => 'Parametros atualizados!'];
    }

    private function normalizarData($data, $label): ?string
    {
        $data = trim((string)$data);
        if ($data === '') return null;

        $dt = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dt || $dt->format('Y-m-d') !== $data) {
            throw new Exception("$label invalida.");
        }

        return $data;
    }
}
