<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Rotas centrais - delega para os ConfigProviders de cada modulo.
 *
 * Cada modulo registra suas rotas no seu ConfigProvider::getRoutes().
 * Este arquivo apenas invoca o registro central.
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {

    // Dashboard
    $app->route('/backend/api/dashboard', \Dashboard\Handler\DashboardHandler::class, ['GET', 'POST'], 'dashboard');

    // ExecucaoFluxo
    $app->route('/backend/api/fluxo', \ExecucaoFluxo\Handler\FluxoHandler::class, ['GET', 'POST', 'DELETE'], 'fluxo');

    // Fornecedores
    $app->route('/backend/api/fornecedores', \Fornecedores\Handler\FornecedoresHandler::class, ['GET', 'POST', 'DELETE'], 'fornecedores');

    // Cotacao
    $app->route('/backend/api/cotacao', \Cotacao\Handler\CotacaoHandler::class, ['GET', 'POST'], 'cotacao');

    // Grade Comparativa
    $app->route('/backend/api/grade', \GradeComparativa\Handler\GradeComparativaHandler::class, ['GET', 'POST'], 'grade');

    // Autorizacao de Compra
    $app->route('/backend/api/autorizacao', \AutorizacaoCompra\Handler\AutorizacaoCompraHandler::class, ['GET', 'POST'], 'autorizacao');

    // Email Fornecedores
    $app->route('/backend/api/email-fornecedores', \EmailFornecedores\Handler\EmailFornecedoresHandler::class, ['GET', 'POST'], 'email-fornecedores');
};
