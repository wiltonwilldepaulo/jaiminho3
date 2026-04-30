<?php

use Doctrine\DBAL\DriverManager;

return DriverManager::getConnection([
    'driver'   => 'pdo_pgsql',
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'     => 5432,
    'dbname'   => $_ENV['DB_NAME'] ?? 'development_db',
    'user'     => $_ENV['DB_USER'] ?? 'senac',
    'password' => $_ENV['DB_PASS'] ?? 'senac',
]);
