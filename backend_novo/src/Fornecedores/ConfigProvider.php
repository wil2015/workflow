<?php

declare(strict_types=1);

namespace Fornecedores;

use Fornecedores\Handler\FornecedoresHandler;
use Fornecedores\Handler\FornecedoresHandlerFactory;

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
                FornecedoresHandler::class => FornecedoresHandlerFactory::class,
            ],
        ];
    }
}
