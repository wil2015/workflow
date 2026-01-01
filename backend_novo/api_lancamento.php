<?php
// backend_novo/api_lancamento.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

// Função para garantir UTF-8
function utf8_enc($str) {
    return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1'); // Ajuste conforme seu banco
}

try {
    $acao = $_GET['acao'] ?? '';
    $idProcesso = $_GET['instance_id'] ?? 0;

    if (!$idProcesso) throw new Exception("ID do processo não informado.");

    // --- ROTA 1: LISTAR ITENS DO PROCESSO (Para a barra lateral) ---
    if ($acao === 'itens') {
        $stmt = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        $vinculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $itens = [];
        if ($connSenior) {
            foreach ($vinculos as $v) {
                // Busca detalhes no Senior
                $sql = "SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
                $stmtS = sqlsrv_query($connSenior, $sql, [$v['num_solicitacao'], $v['seq_solicitacao']]);
                
                if ($stmtS && $row = sqlsrv_fetch_array($stmtS, SQLSRV_FETCH_ASSOC)) {
                    $itens[] = [
                        'num' => $v['num_solicitacao'],
                        'seq' => $v['seq_solicitacao'],
                        'desc' => utf8_encode($row['cplpro']), // Encode básico
                        'qtd' => number_format((float)$row['qtdsol'], 2, ',', '.'),
                        'unid' => trim($row['unimed'])
                    ];
                }
            }
        }
        echo json_encode($itens);

    // --- ROTA 2: BUSCAR COTAÇÕES DE UM ITEM (Para o formulário) ---
    } elseif ($acao === 'cotacao') {
        $num = $_GET['num'];
        $seq = $_GET['seq'];

        // Busca fornecedores vinculados e seus preços (LEFT JOIN)
        $sql = "SELECT 
                    p.id_fornecedor_senior, 
                    p.nome_do_fornecedor,
                    o.valor_unitario
                FROM licitacao_participantes p
                LEFT JOIN licitacao_itens_ofertados o 
                    ON p.id_processo_instancia = o.id_processo_instancia
                    AND p.id_fornecedor_senior = o.id_fornecedor_senior
                    AND o.num_solicitacao = ? 
                    AND o.seq_solicitacao = ?
                WHERE p.id_processo_instancia = ?
                ORDER BY p.nome_do_fornecedor";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$num, $seq, $idProcesso]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fornecedores = [];
        foreach($dados as $d) {
            $fornecedores[] = [
                'cod' => $d['id_fornecedor_senior'],
                'nome' => $d['nome_do_fornecedor'],
                // Retorna valor float para o JS formatar, ou null
                'valor' => $d['valor_unitario'] !== null ? (float)$d['valor_unitario'] : '' 
            ];
        }
        
        echo json_encode($fornecedores);

    } else {
        throw new Exception("Ação inválida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>