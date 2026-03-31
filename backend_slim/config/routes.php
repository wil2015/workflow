<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // =====================================================================
    // Preflight CORS (OPTIONS global)
    // =====================================================================
    $app->options('/{routes:.+}', function ($request, $response) {
        return $response;
    });

    // =====================================================================
    // API Routes - Single Action Handlers (RESTful)
    // =====================================================================
    $app->group('/api', function (RouteCollectorProxy $group) {

        // --- Dashboard ---
        $group->get('/dashboard', \Dashboard\Handler\DashboardHomeHandler::class);

        // --- ExecucaoFluxo ---
        $group->group('/fluxo', function (RouteCollectorProxy $fluxo) {
            $fluxo->get('/tarefa/{id}', \ExecucaoFluxo\Handler\LerTarefaHandler::class);
            $fluxo->get('/solicitacoes', \ExecucaoFluxo\Handler\ListarSolicitacoesHandler::class);
            $fluxo->post('/datas', \ExecucaoFluxo\Handler\SalvarDatasHandler::class);
            $fluxo->post('/vincular', \ExecucaoFluxo\Handler\VincularItensHandler::class);
            $fluxo->post('/remover-item', \ExecucaoFluxo\Handler\RemoverItemHandler::class);
            $fluxo->delete('/processo/{id}', \ExecucaoFluxo\Handler\CancelarProcessoHandler::class);
        });

        // --- Fornecedores ---
        $group->group('/fornecedores', function (RouteCollectorProxy $forn) {
            $forn->get('', \Fornecedores\Handler\ListarFornecedoresHandler::class);
            $forn->post('', \Fornecedores\Handler\SalvarFornecedoresHandler::class);
            $forn->delete('/{processo}/{fornecedor}', \Fornecedores\Handler\RemoverFornecedorHandler::class);
        });

        // --- Cotacao ---
        $group->group('/cotacao', function (RouteCollectorProxy $cot) {
            $cot->get('/itens/{processo}', \Cotacao\Handler\ListarItensCotacaoHandler::class);
            $cot->get('/valores', \Cotacao\Handler\ListarCotacoesHandler::class);
            $cot->post('/valores', \Cotacao\Handler\SalvarCotacaoHandler::class);
        });

        // --- GradeComparativa ---
        $group->group('/grade', function (RouteCollectorProxy $grade) {
            $grade->get('/{processo}', \GradeComparativa\Handler\CarregarGradeHandler::class);
            $grade->post('/consolidar', \GradeComparativa\Handler\ConsolidarGradeHandler::class);
        });

        // --- AutorizacaoCompra ---
        $group->group('/autorizacao', function (RouteCollectorProxy $auth) {
            $auth->get('/{processo}/documentos', \AutorizacaoCompra\Handler\ListarDocumentosHandler::class);
            $auth->post('/{processo}/gerar', \AutorizacaoCompra\Handler\GerarAutorizacoesHandler::class);
            $auth->post('/{processo}/enviar', \AutorizacaoCompra\Handler\EnviarEmailsHandler::class);
        });

        // --- EmailFornecedores ---
        $group->group('/email-fornecedores', function (RouteCollectorProxy $email) {
            $email->get('/{processo}', \EmailFornecedores\Handler\CarregarEmailsHandler::class);
            $email->post('/{processo}', \EmailFornecedores\Handler\SalvarEmailsHandler::class);
            $email->post('/email', \EmailFornecedores\Handler\AdicionarEmailHandler::class);
        });
    });
};
