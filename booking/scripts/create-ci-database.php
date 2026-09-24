<?php

declare(strict_types=1);

$host = getenv('LOCAL_CI_DB_HOST') ?: '127.0.0.1';
$port = getenv('LOCAL_CI_DB_PORT') ?: '3306';
$database = getenv('LOCAL_CI_DB_DATABASE') ?: 'hotel_vastu_booking_ci';
$username = getenv('LOCAL_CI_DB_USERNAME') ?: 'root';
$password = getenv('LOCAL_CI_DB_PASSWORD');

if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Invalid local CI database name.\n");
    exit(2);
}

if (! preg_match('/(_ci|_test)$/', $database)) {
    fwrite(STDERR, "Safety check failed: database name must end in _ci or _test.\n");
    exit(2);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $username,
        $password === false ? '' : $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $database
    ));
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Could not create/check local MySQL CI database: {$exception->getMessage()}\n"
    );
    exit(1);
}

fwrite(STDOUT, "Local MySQL CI database ready: {$database}\n");
