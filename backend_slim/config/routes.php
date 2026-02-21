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
    // API Routes agrupadas sob /api
    // =====================================================================
    $app->group('/api', function (RouteCollectorProxy $group) {

        // --- Dashboard ---
        $group->map(['GET', 'POST'], '/dashboard', \Dashboard\Handler\DashboardHandler::class);

        // --- ExecucaoFluxo ---
        $group->map(['GET', 'POST'], '/fluxo', \ExecucaoFluxo\Handler\FluxoHandler::class);

        // --- Fornecedores ---
        $group->map(['GET', 'POST'], '/fornecedores', \Fornecedores\Handler\FornecedoresHandler::class);

        // --- Cotacao ---
        $group->map(['GET', 'POST'], '/cotacao', \Cotacao\Handler\CotacaoHandler::class);

        // --- GradeComparativa ---
        $group->map(['GET', 'POST'], '/grade', \GradeComparativa\Handler\GradeComparativaHandler::class);

        // --- AutorizacaoCompra ---
        $group->map(['GET', 'POST'], '/autorizacao', \AutorizacaoCompra\Handler\AutorizacaoCompraHandler::class);

        // --- EmailFornecedores ---
        $group->map(['GET', 'POST'], '/email-fornecedores', \EmailFornecedores\Handler\EmailFornecedoresHandler::class);
    });
};
