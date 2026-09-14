<?php

declare(strict_types=1);

namespace App\Migration;

/**
 * Preflight TCP no bloqueante hacia el SQL Server legacy.
 *
 * El connect real de pdo_dblib ignora login_timeout/timeout/ATTR_TIMEOUT en
 * esta imagen y, cuando el host está caído, cuelga ~130s (timeout TCP del
 * kernel) — más que el max_execution_time de un request. SondaLegado abre un
 * socket TCP con timeout corto ANTES de construir el PDO: si el host no
 * responde, lanza PDOException en ~timeout segundos y toda la cadena (catálogo
 * de entidades, indicadores, jobs) degrada rápido y limpio.
 *
 * El resultado se memoiza con TTL corto para que /indicadores (que consulta el
 * legado por entidad) no pague el timeout por cada entidad; el TTL evita que el
 * memo se quede obsoleto si el legado vuelve.
 */
final class SondaLegado
{
    private const TTL_SEGUNDOS = 10;

    private ?bool $ultimoResultado = null;
    private ?int $ultimaComprobacion = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $dbname,
        private readonly string $username,
        private readonly string $password,
        private readonly float $timeout = 5,
    ) {}

    public function accesible(): bool
    {
        $ahora = time();
        if (
            null !== $this->ultimaComprobacion &&
            $ahora - $this->ultimaComprobacion < self::TTL_SEGUNDOS
        ) {
            return $this->ultimoResultado ?? false;
        }

        $s = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );
        $ok = false !== $s;
        if ($s) {
            fclose($s);
        }

        $this->ultimoResultado = $ok;
        $this->ultimaComprobacion = $ahora;

        return $ok;
    }

    /**
     * Factory del servicio PDO $oldPdo: falla rápido si el legado no responde,
     * evitando el cuelgue de ~130s del connect dblib con el host caído.
     */
    public function crearPdo(): \PDO
    {
        if (!$this->accesible()) {
            throw new \PDOException(
                "El servidor SQL Server legacy no responde en " .
                    "{$this->host}:{$this->port} (timeout TCP de {$this->timeout}s).",
            );
        }

        return new \PDO(
            "dblib:host={$this->host}:{$this->port};dbname={$this->dbname}",
            $this->username,
            $this->password,
        );
    }
}
