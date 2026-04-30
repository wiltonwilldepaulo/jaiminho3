<?php

use Doctrine\Migrations\Version\Generator\VersionGenerator;

return [

    #Migration Table
    'table_storage' => [
        'table_name' => 'migration_versions',
    ],

    #Migrations Path
    'migrations_paths' => [
        'app\database\migration' => dirname(__FILE__, 2) . '/migration',
    ],

    'all_or_nothing' => true,

    #Custom Version Generator
    'services' => [

        VersionGenerator::class => new class implements VersionGenerator {

            public function generateVersion(): string
            {
                # timestamp padrão
                $timestamp = date('YmdHis');
                # nome vindo do CLI
                global $argv;
                $name = $argv[2] ?? 'migration';
                #sanitiza nome
                $name = strtolower(
                    preg_replace('/[^a-z0-9]+/i', '_', $name)
                );
                return "{$timestamp}_{$name}";
            }
        }
    ],
];
