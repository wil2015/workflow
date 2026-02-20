<?php

declare(strict_types=1);

namespace Fornecedores\Handler;

use Fornecedores\Repository\FornecedoresRepo;
use Fornecedores\Service\FornecedoresService;
use Psr\Container\ContainerInterface;

class FornecedoresHandlerFactory
{
    public function __invoke(ContainerInterface $container): FornecedoresHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new FornecedoresRepo($pdo, $senior);
        $service = new FornecedoresService($repo);

        return new FornecedoresHandler($service, $pdo);
    }
}
