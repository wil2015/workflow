<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Carrega variaveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Container PHP-DI
$definitions = require __DIR__ . '/../config/container.php';
$container = new \DI\Container($definitions);

// Cria Slim App via PHP-DI Bridge (autowiring)
$app = Bridge::create($container);

// Middleware global
$middleware = require __DIR__ . '/../config/middleware.php';
$middleware($app);

// Rotas modulares
$routes = require __DIR__ . '/../config/routes.php';
$routes($app);

$app->run();
