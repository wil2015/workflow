<?php

declare(strict_types=1);

namespace EmailFornecedores;

use EmailFornecedores\Handler\EmailFornecedoresHandler;
use EmailFornecedores\Handler\EmailFornecedoresHandlerFactory;

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
                EmailFornecedoresHandler::class => EmailFornecedoresHandlerFactory::class,
            ],
        ];
    }
}
