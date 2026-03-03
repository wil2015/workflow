<?php

declare(strict_types=1);

/**
 * Configuracoes locais que le do .env (carregado no public/index.php).
 * Este arquivo NAO deve ser commitado com credenciais reais.
 */
return [
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),

    'db' => [
        'mysql' => [
            'host'   => $_ENV['MYSQL_HOST'] ?? '127.0.0.1',
            'dbname' => $_ENV['MYSQL_DB'] ?? 'fundunesp_workflow',
            'user'   => $_ENV['MYSQL_USER'] ?? 'root',
            'pass'   => $_ENV['MYSQL_PASS'] ?? '',
        ],
        'senior' => [
            'host'   => $_ENV['SENIOR_HOST'] ?? '127.0.0.1',
            'dbname' => $_ENV['SENIOR_DB'] ?? 'sapiens',
            'user'   => $_ENV['SENIOR_USER'] ?? 'sapiens',
            'pass'   => $_ENV['SENIOR_PASS'] ?? 'sapiens',
        ],
    ],

    'smtp_dsn' => $_ENV['SMTP_DSN'] ?? 'smtp://null:null@localhost',
];
