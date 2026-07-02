<?php

declare(strict_types=1);

namespace App\Doctrine\Driver\PDODblib;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PDO\Exception;
use PDO;

final class Driver extends AbstractSQLServerDriver
{
    public function connect(array $params): PDOConnection
    {
        $dsn = $this->constructDsn($params);

        $user = $params['user'] ?? '';
        $password = $params['password'] ?? '';

        try {
            $pdo = new PDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }

        return new PDOConnection($pdo);
    }

    private function constructDsn(array $params): string
    {
        $host = $params['host'] ?? 'localhost';
        $port = $params['port'] ?? 1433;
        $dbname = $params['dbname'] ?? '';

        return sprintf('dblib:host=%s:%s;dbname=%s', $host, $port, $dbname);
    }
}
