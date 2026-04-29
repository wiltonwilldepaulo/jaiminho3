<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function envOrFail(string $key): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        throw new RuntimeException("Missing required environment variable: {$key}");
    }

    return (string) $value;
}

return
    [
        'paths' => [
            'migrations' => '%%PHINX_CONFIG_DIR%%/App/Database/Migration',
            'seeds' => '%%PHINX_CONFIG_DIR%%/App/Database/Seed'
        ],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => 'development',
            'production' => [
                'adapter' => envOrFail('DB_CONNECTION'),
                'host'    => envOrFail('DB_HOST'),
                'name'    => envOrFail('DB_NAME'),
                'user'    => envOrFail('DB_USER'),
                'pass'    => envOrFail('DB_PASSWORD'),
                'port'    => (int) envOrFail('DB_PORT'),
                'charset' => 'utf8',
            ],
            'development' => [
                'adapter' => envOrFail('DB_CONNECTION'),
                'host'    => envOrFail('DB_HOST'),
                'name'    => envOrFail('DB_NAME'),
                'user'    => envOrFail('DB_USER'),
                'pass'    => envOrFail('DB_PASSWORD'),
                'port'    => (int) envOrFail('DB_PORT'),
                'charset' => 'utf8',
            ],
            'testing' => [
                'adapter' => envOrFail('DB_CONNECTION'),
                'host'    => envOrFail('DB_HOST'),
                'name'    => envOrFail('DB_NAME'),
                'user'    => envOrFail('DB_USER'),
                'pass'    => envOrFail('DB_PASSWORD'),
                'port'    => (int) envOrFail('DB_PORT'),
                'charset' => 'utf8',
            ]
        ],
        'version_order' => 'creation'
    ];
