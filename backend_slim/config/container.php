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
$definitions[\Dashboard\Handler\DashboardHandler::class] = function (ContainerInterface $c) {
    return new \Dashboard\Handler\DashboardHandler($c->get(\Dashboard\Service\DashboardService::class));
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
$definitions[\ExecucaoFluxo\Handler\FluxoHandler::class] = function (ContainerInterface $c) {
    return new \ExecucaoFluxo\Handler\FluxoHandler(
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
$definitions[\Fornecedores\Handler\FornecedoresHandler::class] = function (ContainerInterface $c) {
    return new \Fornecedores\Handler\FornecedoresHandler(
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
$definitions[\Cotacao\Handler\CotacaoHandler::class] = function (ContainerInterface $c) {
    return new \Cotacao\Handler\CotacaoHandler(
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
$definitions[\GradeComparativa\Handler\GradeComparativaHandler::class] = function (ContainerInterface $c) {
    return new \GradeComparativa\Handler\GradeComparativaHandler(
        $c->get(\GradeComparativa\Service\GradeComparativaService::class),
        $c->get('pdo.mysql')
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
$definitions[\AutorizacaoCompra\Handler\AutorizacaoCompraHandler::class] = function (ContainerInterface $c) {
    return new \AutorizacaoCompra\Handler\AutorizacaoCompraHandler(
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
$definitions[\EmailFornecedores\Handler\EmailFornecedoresHandler::class] = function (ContainerInterface $c) {
    return new \EmailFornecedores\Handler\EmailFornecedoresHandler(
        $c->get(\EmailFornecedores\Service\EmailFornecedoresService::class),
        $c->get('pdo.mysql')
    );
};

return $definitions;
