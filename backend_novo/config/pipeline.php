<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Helper\ServerUrlMiddleware;
use Mezzio\Helper\UrlHelperMiddleware;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Pipeline de middleware setup.
 *
 * Ordem importa: CORS -> BodyParams -> Routing -> Dispatch
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {

    // 1. CORS (primeiro de tudo, antes de qualquer outra coisa)
    $app->pipe(\App\Middleware\CorsMiddleware::class);

    // 2. Helpers Mezzio
    $app->pipe(ServerUrlMiddleware::class);

    // 3. Routing
    $app->pipe(RouteMiddleware::class);

    // 4. URL Helper (precisa vir depois do RouteMiddleware)
    $app->pipe(UrlHelperMiddleware::class);

    // 5. Metodos implicitos (HEAD, OPTIONS, 405)
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);

    // 6. Dispatch do handler da rota
    $app->pipe(DispatchMiddleware::class);

    // 7. 404 Not Found (fim do pipeline)
    $app->pipe(NotFoundHandler::class);
};
