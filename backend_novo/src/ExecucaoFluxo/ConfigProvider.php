<?php

declare(strict_types=1);

namespace ExecucaoFluxo;

use ExecucaoFluxo\Handler\FluxoHandler;
use ExecucaoFluxo\Handler\FluxoHandlerFactory;

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
                FluxoHandler::class => FluxoHandlerFactory::class,
            ],
        ];
    }
}
