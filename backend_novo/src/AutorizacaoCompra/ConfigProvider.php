<?php

declare(strict_types=1);

namespace AutorizacaoCompra;

use AutorizacaoCompra\Handler\AutorizacaoCompraHandler;
use AutorizacaoCompra\Handler\AutorizacaoCompraHandlerFactory;

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
            'factories' => [
                AutorizacaoCompraHandler::class => AutorizacaoCompraHandlerFactory::class,
            ],
        ];
    }
}
