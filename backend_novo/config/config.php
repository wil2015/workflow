<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

$aggregator = new ConfigAggregator([
    // Modulo global (PDO factories, CORS middleware)
    \App\ConfigProvider::class,

    // Modulos de negocio
    \Dashboard\ConfigProvider::class,
    \ExecucaoFluxo\ConfigProvider::class,
    \Fornecedores\ConfigProvider::class,
    \Cotacao\ConfigProvider::class,
    \GradeComparativa\ConfigProvider::class,
    \AutorizacaoCompra\ConfigProvider::class,
    \EmailFornecedores\ConfigProvider::class,

    // Mezzio defaults
    \Mezzio\ConfigProvider::class,
    \Mezzio\Router\ConfigProvider::class,
    \Mezzio\Router\FastRouteRouter\ConfigProvider::class,

    // Config files em autoload/
    new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
]);

return $aggregator->getMergedConfig();
