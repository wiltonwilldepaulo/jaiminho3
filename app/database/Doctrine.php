<?php

declare(strict_types=1);

namespace app\database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

# Singleton de conexão DBAL — uma instância única por processo PHP
final class Doctrine
{
    # Cache estático: evita abrir nova conexão a cada chamada
    private static ?Connection $conn = null;

    # Retorna a conexão existente ou cria uma nova na primeira chamada
    public static function connection(): Connection
    {
        # Reutiliza conexão já aberta sem custo de I/O
        if (self::$conn !== null) return self::$conn;

        # Fail Fast: valida cada variável obrigatória antes de tentar conectar
        foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $k) {
            if (empty($_ENV[$k])) throw new \RuntimeException("Variável de ambiente '{$k}' não definida no .env.");
        }

        # Cria conexão DBAL com parâmetros vindos exclusivamente do .env
        return self::$conn = DriverManager::getConnection([
            'driver'   => 'pdo_' . $_ENV['DB_CONNECTION'], # Ex: DB_CONNECTION=pgsql → pdo_pgsql
            'host'     => $_ENV['DB_HOST'],
            'port'     => (int) $_ENV['DB_PORT'],
            'dbname'   => $_ENV['DB_NAME'],
            'user'     => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset'  => 'utf8',
        ]);
    }

    # Descarta a conexão ativa — isola estados entre testes unitários
    public static function reset(): void
    {
        self::$conn = null;
    }
}
