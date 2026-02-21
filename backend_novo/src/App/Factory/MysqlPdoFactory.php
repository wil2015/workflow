<?php

declare(strict_types=1);

namespace App\Factory;

use PDO;
use Psr\Container\ContainerInterface;

class MysqlPdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $config = $container->get('config')['db']['mysql'];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['dbname']
        );

        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('SET NAMES utf8mb4');

        return $pdo;
    }
}
