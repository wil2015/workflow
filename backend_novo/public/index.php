<?php

declare(strict_types=1);

// Carrega .env antes de tudo
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/** @var \Psr\Container\ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

/** @var \Mezzio\Application $app */
$app = $container->get(\Mezzio\Application::class);
$factory = $container->get(\Mezzio\MiddlewareFactory::class);

// Executa pipeline e rotas
(require __DIR__ . '/../config/pipeline.php')($app, $factory, $container);
(require __DIR__ . '/../config/routes.php')($app, $factory, $container);

$app->run();
