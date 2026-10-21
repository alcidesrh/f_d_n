<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Lo publicado para los navegadores tras una revisión (ADR-036): la huella
 * del schema GraphQL y la de la configuración de cada entidad, cada una con
 * la revisión en que cambió por última vez. Los navegadores comparan esas
 * revisiones con las suyas; las huellas solo dicen si algo cambió. Puro: lo
 * guarda y lo lee `RegistroRevisiones`.
 */
final class Estado
{
    /**
     * @param array<string, array{huella: string, revision: int}> $configs por entidad
     * @param int $completa última revisión que pidió recargar todo en cada navegador (0: ninguna)
     * @param array{revision: int, nota: ?string}|null $aviso última revisión publicada con aviso
     */
    public function __construct(
        public readonly int $revision,
        public readonly string $esquemaHuella,
        public readonly int $esquemaRevision,
        public readonly array $configs = [],
        public readonly int $completa = 0,
        public readonly ?array $aviso = null,
    ) {}

    /** Antes de la primera revisión. */
    public static function inicial(): self
    {
        return new self(0, "", 0);
    }

    /**
     * Qué cambia si lo vigente es esto.
     *
     * @param string|null $esquema huella del schema; null: no se compara
     * @param array<string, ?string>|null $huellas configuración por entidad (null: ya no tiene); null: no se compara
     * @param bool $todas `$huellas` es toda la configuración: la entidad que no está, se eliminó
     */
    public function cambios(?string $esquema, ?array $huellas = null, bool $todas = false): Cambios
    {
        $esquemaCambio = $esquema !== null && $esquema !== $this->esquemaHuella;
        if ($huellas === null) {
            return new Cambios($esquemaCambio);
        }
        $agregadas = $modificadas = $eliminadas = [];
        foreach ($huellas as $entidad => $huella) {
            $entidad = (string) $entidad;
            $antes = $this->configs[$entidad]["huella"] ?? null;
            if ($huella === null) {
                if ($antes !== null) {
                    $eliminadas[] = $entidad;
                }
            } elseif ($antes === null) {
                $agregadas[] = $entidad;
            } elseif ($antes !== $huella) {
                $modificadas[] = $entidad;
            }
        }
        if ($todas) {
            foreach (array_keys($this->configs) as $entidad) {
                if (!array_key_exists($entidad, $huellas)) {
                    $eliminadas[] = (string) $entidad;
                }
            }
        }
        sort($agregadas);
        sort($modificadas);
        sort($eliminadas);

        return new Cambios($esquemaCambio, $agregadas, $modificadas, $eliminadas);
    }

    /**
     * El estado tras publicar `$cambios` (de `cambios()` con las mismas huellas)
     * como la revisión `$numero`.
     *
     * @param array<string, ?string>|null $huellas
     * @param bool $aviso los navegadores muestran «Recargar» con la `$nota`
     */
    public function siguiente(int $numero, Cambios $cambios, ?string $esquema, ?array $huellas, bool $completa = false, bool $aviso = false, ?string $nota = null): self
    {
        $configs = $this->configs;
        foreach ([...$cambios->agregadas, ...$cambios->modificadas] as $entidad) {
            $configs[$entidad] = ["huella" => (string) ($huellas[$entidad] ?? ""), "revision" => $numero];
        }
        foreach ($cambios->eliminadas as $entidad) {
            unset($configs[$entidad]);
        }
        ksort($configs, SORT_STRING);

        return new self(
            $numero,
            $cambios->esquema ? (string) $esquema : $this->esquemaHuella,
            $cambios->esquema ? $numero : $this->esquemaRevision,
            $configs,
            $completa ? $numero : $this->completa,
            $aviso ? ["revision" => $numero, "nota" => $nota] : $this->aviso,
        );
    }

    /**
     * Lo que leen los navegadores (`GET /api/metadata`): las revisiones, sin las
     * huellas de la configuración.
     *
     * @return array{revision: int, esquema: array{revision: int, huella: string}, configs: object, completa: int, aviso: array{revision: int, nota: ?string}|null}
     */
    public function manifiesto(): array
    {
        return [
            "revision" => $this->revision,
            "esquema" => ["revision" => $this->esquemaRevision, "huella" => $this->esquemaHuella],
            "configs" => (object) array_map(static fn(array $config): int => $config["revision"], $this->configs),
            "completa" => $this->completa,
            "aviso" => $this->aviso,
        ];
    }

    /**
     * Lo que se guarda en `revision_metadata.estado` (sin el número: es el id de la fila).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            "esquema" => ["huella" => $this->esquemaHuella, "revision" => $this->esquemaRevision],
            "configs" => $this->configs,
            "completa" => $this->completa,
            "aviso" => $this->aviso,
        ];
    }

    /** @param array<string, mixed> $datos lo de `toArray()` */
    public static function desde(int $revision, array $datos): self
    {
        $configs = [];
        foreach ((array) ($datos["configs"] ?? []) as $entidad => $config) {
            if (is_array($config) && is_string($config["huella"] ?? null)) {
                $configs[(string) $entidad] = ["huella" => $config["huella"], "revision" => (int) ($config["revision"] ?? 0)];
            }
        }
        $esquema = is_array($datos["esquema"] ?? null) ? $datos["esquema"] : [];
        $aviso = is_array($datos["aviso"] ?? null) ? $datos["aviso"] : null;

        return new self(
            $revision,
            (string) ($esquema["huella"] ?? ""),
            (int) ($esquema["revision"] ?? 0),
            $configs,
            (int) ($datos["completa"] ?? 0),
            $aviso === null ? null : ["revision" => (int) ($aviso["revision"] ?? 0), "nota" => is_string($aviso["nota"] ?? null) ? $aviso["nota"] : null],
        );
    }
}
