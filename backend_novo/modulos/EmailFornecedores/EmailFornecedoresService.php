<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/EmailFornecedoresRepo.php';

class EmailFornecedoresService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior) {
        parent::__construct($pdo, $connSenior);
        $this->repo = new EmailFornecedoresRepo($pdo, $connSenior);
    }

    public function carregarEmails($idProcesso) {
        if (!$idProcesso) throw new Exception("ID obrigatório.");

        $participantes = $this->repo->buscarParticipantes($idProcesso);
        
        foreach ($participantes as $p) {
            $codSenior = $p['id_fornecedor_senior'];
            $emailsSenior = $this->repo->buscarEmailsNoSenior($codSenior);
            foreach ($emailsSenior as $email) {
                $this->repo->upsertEmailMestre($codSenior, $email);
            }
        }

        $raw = $this->repo->buscarEmailsParaSelecao($idProcesso);
        $agrupado = [];
        foreach ($raw as $r) {
            $idPart = $r['id_participante'];
            if (!isset($agrupado[$idPart])) {
                $agrupado[$idPart] = [
                    'id_participante' => $idPart,
                    'id_fornecedor_senior' => $r['id_fornecedor_senior'],
                    'nome' => $this->utf8($r['nome_do_fornecedor']), // HERANÇA
                    'emails' => []
                ];
            }
            $agrupado[$idPart]['emails'][] = [
                'email' => $r['email_fornecedor'],
                'checked' => (bool)$r['selecionado']
            ];
        }

        return array_values($agrupado);
    }

    public function adicionarEmailManual($codFornSenior, $email) {
        if (empty($codFornSenior)) throw new Exception("Fornecedor inválido.");
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail inválido.");

        $this->repo->upsertEmailMestre($codFornSenior, $email);
        return ['sucesso' => true];
    }

    public function salvarEmailsSelecionados($idProcesso, $selecao) {
        $this->repo->limparSelecaoAnterior($idProcesso);
        $count = 0;
        foreach ($selecao as $item) {
            $this->repo->salvarEmailInstancia($idProcesso, $item['id_participante'], $item['id_fornecedor_senior'], $item['email']);
            $count++;
        }
        return ['sucesso' => true, 'msg' => "$count e-mails definidos para envio."];
    }
}