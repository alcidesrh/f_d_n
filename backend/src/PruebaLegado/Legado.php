<?php

declare(strict_types=1);

namespace App\PruebaLegado;

use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * TEMPORAL (prueba de flujos con datos reales; borrar con `src/PruebaLegado/` y
 * `Controller/PruebaLegadoController.php`). Lectura del SQL Server del sistema anterior.
 * Nunca escribe: solo SELECT. Texto en UTF-8 aunque el servidor lo entregue en CP1252.
 */
#[Lazy]
final class Legado
{
    public const ZONA = "America/Guatemala";

    public function __construct(#[Target("oldPdo")] private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<array<string, mixed>>
     */
    public function filas(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map(static fn(array $f) => array_map(self::utf8(...), $f), $st->fetchAll());
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return array<string, mixed>|null
     */
    public function fila(string $sql, array $params = []): ?array
    {
        return $this->filas($sql, $params)[0] ?? null;
    }

    /** Lista de enteros lista para un `IN (...)` (se castea: no hay inyección posible). */
    public static function enteros(array $ids): string
    {
        return $ids === [] ? "NULL" : implode(",", array_map("intval", $ids));
    }

    public static function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("now", new \DateTimeZone(self::ZONA));
    }

    /** Fecha-hora local del legado → ISO con el offset de Guatemala. */
    public static function iso(string $fecha): string
    {
        return (new \DateTimeImmutable($fecha, new \DateTimeZone(self::ZONA)))->format(DATE_ATOM);
    }

    private static function utf8(mixed $valor): mixed
    {
        return is_string($valor) && !mb_check_encoding($valor, "UTF-8") ? mb_convert_encoding($valor, "UTF-8", "Windows-1252") : $valor;
    }
}
