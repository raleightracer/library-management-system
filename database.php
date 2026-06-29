<?php
declare(strict_types=1);

/*
 * Central database entry point kept for compatibility with the existing
 * project. The UI can include this file without opening a database
 * connection; API endpoints call db() when they actually need PDO.
 */

require_once __DIR__ . '/core/Database.php';

function db(): PDO
{
    return Database::connection();
}

function getDbConnection(): PDO
{
    return db();
}

$pdo = null;
$conn = null;
