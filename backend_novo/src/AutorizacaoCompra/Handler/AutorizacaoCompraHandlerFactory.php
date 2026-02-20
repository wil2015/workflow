<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Handler;

use AutorizacaoCompra\Repository\AutorizacaoCompraRepo;
use AutorizacaoCompra\Service\AutorizacaoCompraService;
use Psr\Container\ContainerInterface;

class AutorizacaoCompraHandlerFactory
{
    public function __invoke(ContainerInterface $container): AutorizacaoCompraHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new AutorizacaoCompraRepo($pdo, $senior);
        $service = new AutorizacaoCompraService($repo);

        return new AutorizacaoCompraHandler($service, $pdo);
    }
}
