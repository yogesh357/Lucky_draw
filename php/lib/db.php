<?php

declare(strict_types=1);

class DB
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST']   ?? 'localhost',
                $_ENV['DB_PORT']   ?? '3307',
                $_ENV['DB_NAME']   ?? 'new_design'
            );
            self::$pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+00:00'",
            ]);
        }
        return self::$pdo;
    }

    public static function beginTransaction(): void
    {
        self::get()->beginTransaction();
    }
    public static function commit(): void
    {
        self::get()->commit();
    }
    public static function rollBack(): void
    {
        self::get()->rollBack();
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $r = self::query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): string
    {
        self::query($sql, $params);
        return self::get()->lastInsertId();
    }

    public static function config(string $key): ?string
    {
        $row = self::fetch("SELECT value FROM config WHERE `key` = ?", [$key]);
        return $row ? $row['value'] : null;
    }
}
