<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = Config::get('DB_HOST', 'db');
            $port = Config::get('DB_PORT', '3306');
            $db   = Config::get('DB_DATABASE', 'teacare');
            $user = Config::get('DB_USERNAME', 'teacare_user');
            $pass = Config::get('DB_PASSWORD', 'teacare_pass');

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+06:00'",
            ]);
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): string
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return self::pdo()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($pdo);
            if ($started && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
