<?php

declare(strict_types=1);

namespace App\Migration;

use Symfony\Contracts\Service\ResetInterface;

/**
 * El PDO del legado (`$oldPdo`) para procesos que viven mucho: el worker de
 * FrankenPHP y `app:sincronizar --cada`.
 *
 * Una conexión dblib que se cae (el servidor o la red la cortan tras un rato
 * sin uso, un timeout) queda «DBPROCESS is dead» para siempre, y el PDO es un
 * servicio compartido: sin esto, todo lo que lo usa falla hasta reiniciar el
 * proceso. Aquí la conexión real se abre al primer uso, se comprueba con un
 * `SELECT 1` si estuvo quieta, se suelta al terminar cada petición
 * (`reset`) y se puede soltar tras un error (`soltar`).
 *
 * Extiende \PDO para que los consumidores (tipados `\PDO`) no cambien; no
 * llama al constructor del padre: todo se delega en la conexión real.
 */
class PdoLegado extends \PDO implements ResetInterface
{
    /** Segundos sin uso tras los que se comprueba la conexión antes de usarla. */
    private const COMPROBAR_TRAS = 5;

    private ?\PDO $pdo = null;
    private int $ultimoUso = 0;

    /** @param \Closure(): \PDO $conectar */
    public function __construct(private readonly \Closure $conectar) {}

    /** Suelta la conexión: la próxima operación abre otra (salvo a mitad de una transacción). */
    public function soltar(): void
    {
        if ($this->pdo !== null && !self::enTransaccion($this->pdo)) {
            $this->pdo = null;
        }
    }

    public function reset(): void
    {
        $this->soltar();
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->real()->prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return $fetchMode === null ? $this->real()->query($query) : $this->real()->query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return $this->real()->exec($statement);
    }

    public function beginTransaction(): bool
    {
        return $this->real()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->real()->commit();
    }

    public function rollBack(): bool
    {
        return $this->real()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo !== null && self::enTransaccion($this->pdo);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->real()->lastInsertId($name);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->real()->setAttribute($attribute, $value);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->real()->getAttribute($attribute);
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->real()->quote($string, $type);
    }

    public function errorCode(): ?string
    {
        return $this->pdo?->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->pdo?->errorInfo() ?? ["", null, null];
    }

    private function real(): \PDO
    {
        $ahora = time();
        if ($this->pdo !== null && $ahora - $this->ultimoUso >= self::COMPROBAR_TRAS && !self::enTransaccion($this->pdo)) {
            try {
                // Una conexión muerta a veces no lanza: devuelve vacío. Por eso se mira el 1.
                if ((int) $this->pdo->query("SELECT 1")->fetchColumn() !== 1) {
                    throw new \RuntimeException("La conexión al legado no respondió.");
                }
            } catch (\Throwable) {
                $this->pdo = null;
            }
        }
        $this->pdo ??= ($this->conectar)();
        $this->ultimoUso = $ahora;

        return $this->pdo;
    }

    private static function enTransaccion(\PDO $pdo): bool
    {
        try {
            return $pdo->inTransaction();
        } catch (\Throwable) {
            return false;
        }
    }
}
