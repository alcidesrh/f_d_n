<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Instala o quita en el SQL Server del legado las tablas `sync_*` y los
 * triggers que anotan sus cambios (`sql/instalar.sql`, `sql/desinstalar.sql`).
 * Al instalar deja en `sync_config` cómo llamar a FDN (URL y clave): el
 * bundle del legado lo lee de ahí, sin tocar su configuración.
 */
class InstaladorLegado
{
    public function __construct(
        private readonly ConexionLegado $legado,
        #[Autowire(env: "SINCRONIZACION_URL_FDN")] private readonly string $urlFdn = "",
        #[Autowire(env: "SINCRONIZACION_CLAVE")] private readonly string $clave = "",
    ) {}

    /** @return int lotes ejecutados */
    public function instalar(): int
    {
        $lotes = $this->ejecutar(__DIR__ . "/sql/instalar.sql");
        $this->legado->ejecutar(
            "UPDATE sync_config SET fdn_url = :url, fdn_clave = :clave WHERE id = 1",
            ["url" => $this->urlFdn === "" ? null : rtrim($this->urlFdn, "/"), "clave" => $this->clave === "" ? null : $this->clave],
        );

        return $lotes;
    }

    /** Qué hace el legado si FDN no responde al apartar: `permitir` (vende igual; defecto) o `bloquear` (no vende). */
    public function siFalla(string $modo): void
    {
        if (!in_array($modo, ["bloquear", "permitir"], true)) {
            throw new \InvalidArgumentException("Modo desconocido: {$modo} (bloquear o permitir).");
        }
        $this->legado->ejecutar("UPDATE sync_config SET si_falla = :m WHERE id = 1", ["m" => $modo]);
    }

    /** @return int lotes ejecutados */
    public function desinstalar(): int
    {
        return $this->ejecutar(__DIR__ . "/sql/desinstalar.sql");
    }

    /** @return list<string> los lotes del script, separados por `GO` como en sqlcmd */
    public static function lotes(string $sql): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/^\s*GO\s*$/mi', $sql) ?: []),
            static fn(string $lote) => trim((string) preg_replace('/^\s*--.*$/m', "", $lote)) !== "",
        ));
    }

    private function ejecutar(string $archivo): int
    {
        $lotes = self::lotes((string) file_get_contents($archivo));
        foreach ($lotes as $lote) {
            $this->legado->ejecutar($lote);
        }

        return count($lotes);
    }
}
