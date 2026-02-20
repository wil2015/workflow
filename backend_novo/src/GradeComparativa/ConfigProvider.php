<?php

declare(strict_types=1);

namespace GradeComparativa;

use GradeComparativa\Handler\GradeComparativaHandler;
use GradeComparativa\Handler\GradeComparativaHandlerFactory;

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
                GradeComparativaHandler::class => GradeComparativaHandlerFactory::class,
            ],
        ];
    }
}
