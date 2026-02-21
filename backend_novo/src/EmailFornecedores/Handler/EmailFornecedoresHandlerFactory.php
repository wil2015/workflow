<?php

declare(strict_types=1);

namespace EmailFornecedores\Handler;

use EmailFornecedores\Repository\EmailFornecedoresRepo;
use EmailFornecedores\Service\EmailFornecedoresService;
use Psr\Container\ContainerInterface;

class EmailFornecedoresHandlerFactory
{
    public function __invoke(ContainerInterface $container): EmailFornecedoresHandler
    {
        $pdo = $container->get('MysqlPdo');
        $senior = $container->get('SeniorPdo');

        $repo = new EmailFornecedoresRepo($pdo, $senior);
        $service = new EmailFornecedoresService($repo);

        return new EmailFornecedoresHandler($service, $pdo);
    }
}
