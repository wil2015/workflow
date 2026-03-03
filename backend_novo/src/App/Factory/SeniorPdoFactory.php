<?php

declare(strict_types=1);

namespace App\Factory;

use PDO;
use Psr\Container\ContainerInterface;

class SeniorPdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $config = $container->get('config')['db']['senior'];

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1;Encrypt=1',
            $config['host'],
            $config['dbname']
        );

        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
