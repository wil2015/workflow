<?php
require_once 'EmailFornecedoresRepo.php';

class EmailFornecedoresService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new EmailFornecedoresRepo($pdo, $connSenior);
    }

    public function carregarEmails($idProcesso) {
        if (!$idProcesso) throw new Exception("ID do processo obrigatório.");

        // 1. Sincroniza (Traz do Senior)
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        foreach ($participantes as $p) {
            $codSenior = $p['id_fornecedor_senior'];
            $emailsSenior = $this->repo->buscarEmailsNoSenior($codSenior);
            foreach ($emailsSenior as $email) {
                // Upsert garante que não duplica se for igual, mas mantém se for diferente
                $this->repo->upsertEmailMestre($codSenior, $email);
            }
        }

        // 2. Busca TODOS os e-mails (Senior + Manuais)
        $raw = $this->repo->buscarEmailsParaSelecao($idProcesso);

        $agrupado = [];
        foreach ($raw as $r) {
            $idPart = $r['id_participante'];
            if (!isset($agrupado[$idPart])) {
                $agrupado[$idPart] = [
                    'id_participante' => $idPart,
                    'id_fornecedor_senior' => $r['id_fornecedor_senior'],
                    'nome' => $this->utf8($r['nome_do_fornecedor']),
                    'emails' => [] // Lista que vai receber múltiplos e-mails
                ];
            }
            // Adiciona cada e-mail encontrado para este fornecedor na lista
            $agrupado[$idPart]['emails'][] = [
                'email' => $r['email_fornecedor'],
                'checked' => (bool)$r['selecionado']
            ];
        }

        return array_values($agrupado);
    }

    public function salvarEmailsSelecionados($idProcesso, $selecao) {
        $this->repo->limparSelecaoAnterior($idProcesso);
        $count = 0;
        foreach ($selecao as $item) {
            $this->repo->salvarEmailInstancia(
                $idProcesso,
                $item['id_participante'],
                $item['id_fornecedor_senior'],
                $item['email']
            );
            $count++;
        }
        return ['sucesso' => true, 'msg' => "$count e-mails vinculados ao processo."];
    }

    public function adicionarEmailManual($codFornSenior, $email) {
        if (empty($codFornSenior)) throw new Exception("Código do fornecedor inválido.");
        
        $emailLimpo = strtolower(trim($email));
        if (!filter_var($emailLimpo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido: $email");
        }

        // Insere na tabela mestre. Como a chave é (cod + email), 
        // se o email for diferente dos existentes, cria uma nova linha.
        $this->repo->upsertEmailMestre($codFornSenior, $emailLimpo);

        return ['sucesso' => true, 'msg' => 'E-mail adicionado!'];
    }

    private function utf8($str) {
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str);
        }
        return $str;
    }
}
?>