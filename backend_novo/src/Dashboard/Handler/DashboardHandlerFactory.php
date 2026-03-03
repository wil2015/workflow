<?php

declare(strict_types=1);

namespace Dashboard\Handler;

use Dashboard\Repository\DashboardRepo;
use Dashboard\Service\DashboardService;
use Psr\Container\ContainerInterface;

class DashboardHandlerFactory
{
    public function __invoke(ContainerInterface $container): DashboardHandler
    {
        $pdo = $container->get('MysqlPdo');

        $repo = new DashboardRepo($pdo);
        $service = new DashboardService($repo);

        return new DashboardHandler($service);
    }
}
