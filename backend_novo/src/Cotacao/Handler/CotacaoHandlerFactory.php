<?php

declare(strict_types=1);

namespace Cotacao\Handler;

use Cotacao\Repository\CotacaoRepo;
use Cotacao\Service\CotacaoService;
use Psr\Container\ContainerInterface;

class CotacaoHandlerFactory
{
    public function __invoke(ContainerInterface $container): CotacaoHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new CotacaoRepo($pdo, $senior);
        $service = new CotacaoService($repo);

        return new CotacaoHandler($service, $pdo);
    }
}
