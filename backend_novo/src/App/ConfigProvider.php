<?php

declare(strict_types=1);

namespace App;

use App\Factory\MysqlPdoFactory;
use App\Factory\SeniorPdoFactory;
use App\Middleware\CorsMiddleware;
use PDO;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases' => [],
            'factories' => [
                // Conexoes de banco
                'MysqlPdo'    => MysqlPdoFactory::class,
                'SeniorPdo'   => SeniorPdoFactory::class,

                // Middleware
                CorsMiddleware::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            ],
        ];
    }
}
