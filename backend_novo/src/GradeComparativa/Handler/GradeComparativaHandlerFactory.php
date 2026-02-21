<?php

declare(strict_types=1);

namespace GradeComparativa\Handler;

use GradeComparativa\Repository\GradeComparativaRepo;
use GradeComparativa\Service\GradeComparativaService;
use Psr\Container\ContainerInterface;

class GradeComparativaHandlerFactory
{
    public function __invoke(ContainerInterface $container): GradeComparativaHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new GradeComparativaRepo($pdo, $senior);
        $service = new GradeComparativaService($repo);

        return new GradeComparativaHandler($service, $pdo);
    }
}
