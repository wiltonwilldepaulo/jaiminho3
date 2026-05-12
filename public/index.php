<?php

declare(strict_types=1);

$senha = '123';

$hash = password_hash($senha, PASSWORD_DEFAULT);

var_dump($hash);

die;


$app = require __DIR__ . '/../app/bootstrap.php';

$app->run();
