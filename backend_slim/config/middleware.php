<?php

declare(strict_types=1);

use Slim\App;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyParserMiddleware;

return function (App $app) {
    // Tratamento de erros (Slim built-in)
    $displayErrors = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    $errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);

    // JSON Body Parser (para requests com Content-Type: application/json)
    $app->add(new JsonBodyParserMiddleware());

    // CORS
    $app->add(new CorsMiddleware());

    // Routing middleware (necessario para Slim 4)
    $app->addRoutingMiddleware();
};
