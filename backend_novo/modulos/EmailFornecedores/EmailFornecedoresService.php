<?php
require_once 'EmailFornecedoresRepo.php';

class EmailFornecedoresService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new EmailFornecedoresRepo($pdo, $connSenior);
    }

    public function carregarEmails($idProcesso) {
        if (!$idProcesso) throw new Exception("ID obrigatório.");

        // 1. SINCRONIZAÇÃO: Busca novidades do Senior e guarda no histórico
        $participantes = $this->repo->buscarParticipantes($idProcesso);
        
        foreach ($participantes as $p) {
            $codSenior = $p['id_fornecedor_senior'];
            
            // Busca e-mails no ERP (pode vir vazio se não tiver lá)
            $emailsSenior = $this->repo->buscarEmailsNoSenior($codSenior);
            
            foreach ($emailsSenior as $email) {
                // Guarda no banco local para o futuro. 
                // Se o e-mail já existir (foi inserido manualmente antes ou veio de outra sync), ele apenas ignora.
                $this->repo->upsertEmailMestre($codSenior, $email);
            }
        }

        // 2. LEITURA MISTA: Busca tudo que temos no banco local para estes fornecedores
        // Isso inclui o que acabamos de trazer do Senior E o que foi cadastrado manualmente mês passado.
        $raw = $this->repo->buscarEmailsParaSelecao($idProcesso);

        // Agrupa para o Frontend (Vue)
        $agrupado = [];
        foreach ($raw as $r) {
            $idPart = $r['id_participante'];
            
            if (!isset($agrupado[$idPart])) {
                $agrupado[$idPart] = [
                    'id_participante' => $idPart,
                    'id_fornecedor_senior' => $r['id_fornecedor_senior'],
                    'nome' => $this->utf8($r['nome_do_fornecedor']),
                    'emails' => []
                ];
            }
            
            $agrupado[$idPart]['emails'][] = [
                'email' => $r['email_fornecedor'],
                'checked' => (bool)$r['selecionado'] // Já vem marcado se salvou anteriormente neste processo
            ];
        }

        return array_values($agrupado);
    }

    public function adicionarEmailManual($codFornSenior, $email) {
        if (empty($codFornSenior)) throw new Exception("Fornecedor inválido.");
        
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail inválido.");

        // Ao adicionar manual, gravamos na tabela MESTRE (email_fornecedor).
        // Assim, na próxima licitação desse fornecedor, esse e-mail aparecerá automaticamente.
        $this->repo->upsertEmailMestre($codFornSenior, $email);

        return ['sucesso' => true];
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
        return ['sucesso' => true, 'msg' => "$count e-mails definidos para envio."];
    }

    private function utf8($str) {
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str);
        }
        return $str;
    }
}
?>