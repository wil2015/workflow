<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use PDO;

// ========================================================================
// MODULO: App (Infraestrutura)
// ========================================================================

$definitions = [
    // PDO MySQL
    'pdo.mysql' => function () {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['MYSQL_HOST'],
            $_ENV['MYSQL_DB']
        );
        $pdo = new PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    },

    // PDO SQL Server Senior
    'pdo.senior' => function () {
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1;Encrypt=1',
            $_ENV['SENIOR_HOST'],
            $_ENV['SENIOR_DB']
        );
        $pdo = new PDO($dsn, $_ENV['SENIOR_USER'], $_ENV['SENIOR_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    },
];

// ========================================================================
// MODULO: Dashboard (Vertical Slice)
// ========================================================================
$definitions[\Dashboard\Repository\DashboardRepo::class] = function (ContainerInterface $c) {
    return new \Dashboard\Repository\DashboardRepo($c->get('pdo.mysql'));
};
$definitions[\Dashboard\Service\DashboardService::class] = function (ContainerInterface $c) {
    return new \Dashboard\Service\DashboardService($c->get(\Dashboard\Repository\DashboardRepo::class));
};
$definitions[\Dashboard\Handler\DashboardHomeHandler::class] = function (ContainerInterface $c) {
    return new \Dashboard\Handler\DashboardHomeHandler($c->get(\Dashboard\Service\DashboardService::class));
};

// ========================================================================
// MODULO: ExecucaoFluxo (Vertical Slice)
// ========================================================================
$definitions[\ExecucaoFluxo\Repository\FluxoRepo::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Repository\FluxoRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\ExecucaoFluxo\Service\FluxoService::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Service\FluxoService($c->get(\ExecucaoFluxo\Repository\FluxoRepo::class));
};
// Single Action Handlers - ExecucaoFluxo
$definitions[\ExecucaoFluxo\Handler\LerTarefaHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\LerTarefaHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class)
    );
};
$definitions[\ExecucaoFluxo\Handler\SalvarDatasHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\SalvarDatasHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class),
        $c->get('pdo.mysql')
    );
};
$definitions[\ExecucaoFluxo\Handler\VincularItensHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\VincularItensHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class),
        $c->get('pdo.mysql')
    );
};
$definitions[\ExecucaoFluxo\Handler\ListarSolicitacoesHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\ListarSolicitacoesHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class)
    );
};
$definitions[\ExecucaoFluxo\Handler\RemoverItemHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\RemoverItemHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class),
        $c->get('pdo.mysql')
    );
};
$definitions[\ExecucaoFluxo\Handler\CancelarProcessoHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\CancelarProcessoHandler(
        $c->get(\ExecucaoFluxo\Service\FluxoService::class),
        $c->get('pdo.mysql')
    );
};

// ========================================================================
// MODULO: Fornecedores (Vertical Slice)
// ========================================================================
$definitions[\Fornecedores\Repository\FornecedoresRepo::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Repository\FornecedoresRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\Fornecedores\Service\FornecedoresService::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Service\FornecedoresService($c->get(\Fornecedores\Repository\FornecedoresRepo::class));
};
// Single Action Handlers - Fornecedores
$definitions[\Fornecedores\Handler\ListarFornecedoresHandler::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Handler\ListarFornecedoresHandler(
        $c->get(\Fornecedores\Service\FornecedoresService::class)
    );
};
$definitions[\Fornecedores\Handler\SalvarFornecedoresHandler::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Handler\SalvarFornecedoresHandler(
        $c->get(\Fornecedores\Service\FornecedoresService::class),
        $c->get('pdo.mysql')
    );
};
$definitions[\Fornecedores\Handler\RemoverFornecedorHandler::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Handler\RemoverFornecedorHandler(
        $c->get(\Fornecedores\Service\FornecedoresService::class),
        $c->get('pdo.mysql')
    );
};

// ========================================================================
// MODULO: Cotacao (Vertical Slice)
// ========================================================================
$definitions[\Cotacao\Repository\CotacaoRepo::class] = function (ContainerInterface $c) {
    return new \Cotacao\Repository\CotacaoRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\Cotacao\Service\CotacaoService::class] = function (ContainerInterface $c) {
    return new \Cotacao\Service\CotacaoService($c->get(\Cotacao\Repository\CotacaoRepo::class));
};
// Single Action Handlers - Cotacao
$definitions[\Cotacao\Handler\ListarItensCotacaoHandler::class] = function (ContainerInterface $c) {
    return new \Cotacao\Handler\ListarItensCotacaoHandler(
        $c->get(\Cotacao\Service\CotacaoService::class)
    );
};
$definitions[\Cotacao\Handler\ListarCotacoesHandler::class] = function (ContainerInterface $c) {
    return new \Cotacao\Handler\ListarCotacoesHandler(
        $c->get(\Cotacao\Service\CotacaoService::class)
    );
};
$definitions[\Cotacao\Handler\SalvarCotacaoHandler::class] = function (ContainerInterface $c) {
    return new \Cotacao\Handler\SalvarCotacaoHandler(
        $c->get(\Cotacao\Service\CotacaoService::class),
        $c->get('pdo.mysql')
    );
};

// ========================================================================
// MODULO: GradeComparativa (Vertical Slice)
// ========================================================================
$definitions[\GradeComparativa\Repository\GradeComparativaRepo::class] = function (ContainerInterface $c) {
    return new \GradeComparativa\Repository\GradeComparativaRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\GradeComparativa\Service\GradeComparativaService::class] = function (ContainerInterface $c) {
    return new \GradeComparativa\Service\GradeComparativaService($c->get(\GradeComparativa\Repository\GradeComparativaRepo::class));
};
// Single Action Handlers - GradeComparativa
$definitions[\GradeComparativa\Handler\CarregarGradeHandler::class] = function (ContainerInterface $c) {
    return new \GradeComparativa\Handler\CarregarGradeHandler(
        $c->get(\GradeComparativa\Service\GradeComparativaService::class)
    );
};
$definitions[\GradeComparativa\Handler\ConsolidarGradeHandler::class] = function (ContainerInterface $c) {
    return new \GradeComparativa\Handler\ConsolidarGradeHandler(
        $c->get(\GradeComparativa\Service\GradeComparativaService::class)
    );
};

// ========================================================================
// MODULO: AutorizacaoCompra (Vertical Slice)
// ========================================================================
$definitions[\AutorizacaoCompra\Repository\AutorizacaoCompraRepo::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Repository\AutorizacaoCompraRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\AutorizacaoCompra\Service\AutorizacaoCompraService::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Service\AutorizacaoCompraService($c->get(\AutorizacaoCompra\Repository\AutorizacaoCompraRepo::class));
};
// Single Action Handlers - AutorizacaoCompra
$definitions[\AutorizacaoCompra\Handler\ListarDocumentosHandler::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Handler\ListarDocumentosHandler(
        $c->get(\AutorizacaoCompra\Service\AutorizacaoCompraService::class)
    );
};
$definitions[\AutorizacaoCompra\Handler\GerarAutorizacoesHandler::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Handler\GerarAutorizacoesHandler(
        $c->get(\AutorizacaoCompra\Service\AutorizacaoCompraService::class)
    );
};
$definitions[\AutorizacaoCompra\Handler\EnviarEmailsHandler::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Handler\EnviarEmailsHandler(
        $c->get(\AutorizacaoCompra\Service\AutorizacaoCompraService::class),
        $c->get('pdo.mysql')
    );
};

// ========================================================================
// MODULO: EmailFornecedores (Vertical Slice)
// ========================================================================
$definitions[\EmailFornecedores\Repository\EmailFornecedoresRepo::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Repository\EmailFornecedoresRepo($c->get('pdo.mysql'), $c->get('pdo.senior'));
};
$definitions[\EmailFornecedores\Service\EmailFornecedoresService::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Service\EmailFornecedoresService($c->get(\EmailFornecedores\Repository\EmailFornecedoresRepo::class));
};
// Single Action Handlers - EmailFornecedores
$definitions[\EmailFornecedores\Handler\CarregarEmailsHandler::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Handler\CarregarEmailsHandler(
        $c->get(\EmailFornecedores\Service\EmailFornecedoresService::class)
    );
};
$definitions[\EmailFornecedores\Handler\SalvarEmailsHandler::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Handler\SalvarEmailsHandler(
        $c->get(\EmailFornecedores\Service\EmailFornecedoresService::class),
        $c->get('pdo.mysql')
    );
};
$definitions[\EmailFornecedores\Handler\AdicionarEmailHandler::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Handler\AdicionarEmailHandler(
        $c->get(\EmailFornecedores\Service\EmailFornecedoresService::class)
    );
};

return $definitions;
