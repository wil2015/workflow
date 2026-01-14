<?php
require_once 'CotacaoRepo.php';

class CotacaoService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        $this->repo = new CotacaoRepo($pdo, $connSenior);
    }

    public function listarItensComDetalhes($idProcesso) {
        if (!$idProcesso) throw new Exception("ID do processo obrigatório.");

        $vinculos = $this->repo->buscarItensDoProcesso($idProcesso);
        $itensCompletos = [];

        foreach ($vinculos as $v) {
            $detalhe = $this->repo->buscarDetalheSenior($v['num_solicitacao'], $v['seq_solicitacao']);
            
            $desc = $detalhe ? $detalhe['cplpro'] : "Item não encontrado";
            $qtd  = $detalhe ? $detalhe['qtdsol'] : 0;
            $unid = $detalhe ? $detalhe['unimed'] : "--";

            $itensCompletos[] = [
                'num' => $v['num_solicitacao'],
                'seq' => $v['seq_solicitacao'],
                'desc' => $this->utf8($desc),
                'qtd' => number_format((float)$qtd, 2, ',', '.'),
                'unid' => trim($unid)
            ];
        }
        return $itensCompletos;
    }

    public function buscarCotacoesDoItem($params) {
        $idProcesso = $params['id_processo'] ?? 0;
        $num = $params['num'] ?? 0;
        $seq = $params['seq'] ?? 0;

        $raw = $this->repo->buscarFornecedoresEValores($idProcesso, $num, $seq);
        
        $lista = [];
        foreach($raw as $r) {
            $lista[] = [
                'cod' => $r['id_fornecedor_senior'],
                'nome' => $this->utf8($r['nome_do_fornecedor']),
                'valor' => $r['valor_unitario'] !== null ? (float)$r['valor_unitario'] : '' 
            ];
        }
        return $lista;
    }

    // --- NOVA FUNÇÃO: SALVAR EM LOTE (Manual) ---
    public function salvarLote($dados) {
        $idProc = $dados['id_processo'] ?? null;
        $numSol = $dados['num_solicitacao'] ?? null;
        $seqSol = $dados['seq_solicitacao'] ?? null;
        $cotacoes = $dados['cotacoes'] ?? []; // Array de {cod, valor}

        if (!$idProc || !$numSol || !$seqSol) {
            throw new Exception("Identificação do item incompleta.");
        }

        $count = 0;
        foreach ($cotacoes as $c) {
            $codForn = $c['cod'] ?? null;
            $valorInput = $c['valor'] ?? null;

            if (!$codForn) continue;

            // Formata valor (PT-BR -> Float)
            $valorFloat = null;
            if ($valorInput !== '' && $valorInput !== null) {
                // Remove pontos de milhar e troca vírgula por ponto
                $v = str_replace('.', '', $valorInput); 
                $v = str_replace(',', '.', $v);
                $valorFloat = (float)$v;
            }

            // Reusa o método do Repo que já funciona bem
            $this->repo->salvarValorUnitario($idProc, $numSol, $seqSol, $codForn, $valorFloat);
            $count++;
        }

        return ['sucesso' => true, 'msg' => "Valores salvos com sucesso!"];
    }

    private function utf8($str) {
        // Verifica se a string JÁ É UTF-8. Se for, não faz nada.
        // Se NÃO for (retornar false), aí sim converte.
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str); // Converte ISO-8859-1 para UTF-8
        }
        return $str; // Já estava correto
    }
}