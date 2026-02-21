<?php

declare(strict_types=1);

namespace EmailFornecedores\Service;

use EmailFornecedores\Repository\EmailFornecedoresRepo;
use Shared\Utils\Formatador;
use Exception;

class EmailFornecedoresService
{
    private EmailFornecedoresRepo $repo;

    public function __construct(EmailFornecedoresRepo $repo)
    {
        $this->repo = $repo;
    }

    public function carregarEmails($idProcesso): array
    {
        if (!$idProcesso) throw new Exception("ID obrigatorio.");

        $participantes = $this->repo->buscarParticipantes($idProcesso);
        foreach ($participantes as $p) {
            $emails = $this->repo->buscarEmailsNoSenior($p['id_fornecedor_senior']);
            foreach ($emails as $email) $this->repo->upsertEmailMestre($p['id_fornecedor_senior'], $email);
        }

        $raw = $this->repo->buscarEmailsParaSelecao($idProcesso);
        $agrupado = [];

        foreach ($raw as $r) {
            $idPart = $r['id_participante'];
            if (!isset($agrupado[$idPart])) {
                $agrupado[$idPart] = [
                    'id_participante' => $idPart,
                    'id_fornecedor_senior' => $r['id_fornecedor_senior'],
                    'nome' => Formatador::utf8($r['nome_do_fornecedor']),
                    'emails' => [],
                ];
            }
            $agrupado[$idPart]['emails'][] = [
                'email' => $r['email_fornecedor'],
                'checked' => (bool)$r['selecionado'],
            ];
        }
        return array_values($agrupado);
    }

    public function adicionarEmailManual($codFornSenior, $email): array
    {
        if (empty($codFornSenior)) throw new Exception("Fornecedor invalido.");
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail invalido.");
        $this->repo->upsertEmailMestre($codFornSenior, $email);
        return ['sucesso' => true];
    }

    public function salvarEmailsSelecionados($idProcesso, $selecao): array
    {
        $this->repo->limparSelecaoAnterior($idProcesso);
        $count = 0;
        foreach ($selecao as $item) {
            $this->repo->salvarEmailInstancia($idProcesso, $item['id_participante'], $item['id_fornecedor_senior'], $item['email']);
            $count++;
        }
        return ['sucesso' => true, 'msg' => "$count e-mails definidos para envio."];
    }
}
