<?php
namespace App\Modulos\Parametros;

use App\Core\BaseRepository;
use PDO;

class ParametrosRepo extends BaseRepository
{
    public function buscarPorProcesso($idProcesso)
    {
        $sql = "SELECT
                    id,
                    data_esperada_da_cotacao,
                    data_esperada_do_recebimento,
                    endereco_da_entrega
                FROM processos_instancia
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function atualizar($idProcesso, $dtCotacao, $dtRecebimento, $enderecoEntrega)
    {
        $sql = "UPDATE processos_instancia
                SET
                    data_esperada_da_cotacao = :dt_cot,
                    data_esperada_do_recebimento = :dt_rec,
                    endereco_da_entrega = :endereco
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':dt_cot' => $dtCotacao,
            ':dt_rec' => $dtRecebimento,
            ':endereco' => $enderecoEntrega,
            ':id' => $idProcesso
        ]);
    }
}
