<?php
// Caminho: backend_novo/modulos/EmailFornecedores/EmailFornecedoresService.php

require_once __DIR__ . '/../../core/BaseService.php';
// 1. Carrega o Engine (Core)
require_once __DIR__ . '/../../core/Documentos/Engine/DocumentoEngine.php';
// 2. Carrega o Documento deste Módulo
require_once __DIR__ . '/Documentos/OrcamentoDoc.php';

// Imports necessários para configurar o Mailer (se não estiver no bootstrap)
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;

class EmailFornecedoresService extends BaseService {
    
    // Método principal chamado pelo Controller
    public function enviarSolicitacaoCotacao($idProcesso, $idsFornecedores = []) {
        
        // A. Configura Mailer (Copie do .env ou config global)
        $dsn = 'smtp://usuario:senha@smtp.mailtrap.io:2525';
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        // B. Instancia a Engine
        $engine = new DocumentoEngine($mailer);

        // C. Busca dados reais (Simulação aqui, substitua pelos seus Repos)
        // $itens = $this->repo->buscarItensDoProcesso($idProcesso);
        // $listaForn = $this->repo->buscarFornecedores($idsFornecedores);
        
        // DADOS FAKE PARA TESTE:
        $itens = [
            ['descricao' => 'Caneta Azul', 'quantidade' => 100, 'unidade' => 'CX'],
            ['descricao' => 'Papel A4', 'quantidade' => 50, 'unidade' => 'RESMA']
        ];
        
        $listaForn = [
            ['id' => 10, 'nome' => 'Papelaria Silva', 'email' => 'vendas@silva.com'],
            ['id' => 11, 'nome' => 'Kalunga Distribuidora', 'email' => 'contato@kalunga.com']
        ];

        $logs = [];

        // D. Loop de Envio
        foreach ($listaForn as $forn) {
            
            // 1. Prepara os dados para o Twig
            $dadosParaTemplate = [
                'id_fornecedor'   => $forn['id'],
                'fornecedor_nome' => $forn['nome'],
                'numero_processo' => $idProcesso . '/2026',
                'data_limite'     => date('d/m/Y', strtotime('+5 days')),
                'itens'           => $itens
            ];

            // 2. Cria o objeto do Documento (OrcamentoDoc)
            $doc = new OrcamentoDoc($dadosParaTemplate);

            // 3. O Engine faz a mágica (PDF + Email)
            try {
                $caminhoArquivo = $engine->processar($doc, $idProcesso, $forn['email']);
                $logs[] = "Enviado para {$forn['nome']} (PDF salvo em: $caminhoArquivo)";
            } catch (Exception $e) {
                $logs[] = "Erro ao enviar para {$forn['nome']}: " . $e->getMessage();
            }
        }

        return $logs;
    }
}