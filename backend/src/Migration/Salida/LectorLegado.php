<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Lecturas del SQL Server del legado para la migración por salida, con las
 * cadenas convertidas de ISO-8859-1 a UTF-8.
 */
class LectorLegado
{
    public function __construct(#[Target("oldPdo")] private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function filas(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(self::utf8(...), $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fila(string $sql, array $params = []): ?array
    {
        return $this->filas($sql, $params)[0] ?? null;
    }

    /**
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    private static function utf8(array $fila): array
    {
        foreach ($fila as $k => $v) {
            if (is_string($v)) {
                $fila[$k] = mb_convert_encoding($v, "UTF-8", "ISO-8859-1");
            }
        }

        return $fila;
    }
}
