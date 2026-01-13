<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$dbPath = $config['db_path'];
$dsn = 'sqlite:' . $dbPath;

$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

return $pdo;
