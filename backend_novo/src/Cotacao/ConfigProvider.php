<?php

declare(strict_types=1);

namespace Cotacao;

use Cotacao\Handler\CotacaoHandler;
use Cotacao\Handler\CotacaoHandlerFactory;

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
                CotacaoHandler::class => CotacaoHandlerFactory::class,
            ],
        ];
    }
}
