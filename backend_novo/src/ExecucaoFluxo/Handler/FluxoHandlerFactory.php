<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Handler;

use ExecucaoFluxo\Repository\FluxoRepo;
use ExecucaoFluxo\Service\FluxoService;
use Psr\Container\ContainerInterface;

class FluxoHandlerFactory
{
    public function __invoke(ContainerInterface $container): FluxoHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new FluxoRepo($pdo, $senior);
        $service = new FluxoService($repo);

        return new FluxoHandler($service, $pdo);
    }
}
