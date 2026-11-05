<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Entity\ConfiguracionBitacora;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lee `ConfiguracionBitacora`. La política que consulta cada petición se
 * guarda en memoria del proceso hasta `TTL` segundos (no es `ResetInterface`
 * a propósito: en el worker de FrankenPHP sobrevive entre peticiones y no
 * agrega una consulta a cada una). Un cambio rige enseguida en el proceso
 * que lo guardó y en los demás a más tardar en `TTL` segundos.
 */
class AjustesBitacora
{
    public const TTL = 30;

    private ?Politica $politica = null;
    private float $leidaEn = 0.0;

    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?ConfiguracionBitacora $fija = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?Connection $db = null,
    ) {}

    /** Ajustes que no vienen de la base (pruebas). */
    public static function fijos(ConfiguracionBitacora $configuracion): self
    {
        return new self(null, $configuracion);
    }

    /** La configuración guardada (o la de por defecto), leída ahora. */
    public function actual(): ConfiguracionBitacora
    {
        return $this->fija
            ?? $this->em?->find(ConfiguracionBitacora::class, ConfiguracionBitacora::ID)
            ?? new ConfiguracionBitacora();
    }

    public function politica(): Politica
    {
        if ($this->politica === null || microtime(true) - $this->leidaEn > self::TTL) {
            try {
                $this->politica = Politica::desde($this->fija ?? $this->leer());
            } catch (\Throwable $e) {
                // Sin la tabla (antes de migrar) o sin base: los valores por defecto.
                $this->logger->warning("No se pudo leer la configuración de la bitácora: {error}", ["error" => $e->getMessage()]);
                $this->politica = Politica::desde(new ConfiguracionBitacora());
            }
            $this->leidaEn = microtime(true);
        }

        return $this->politica;
    }

    /**
     * La fila por DBAL, sin pasar por la unidad de trabajo: se consulta
     * también dentro de un flush (`RegistradorCambios`).
     */
    private function leer(): ConfiguracionBitacora
    {
        $c = new ConfiguracionBitacora();
        $f = ($this->db ?? $this->em?->getConnection())?->fetchAssociative("SELECT * FROM configuracion_bitacora WHERE id = ?", [ConfiguracionBitacora::ID]);
        if ($f === null || $f === false) {
            return $c;
        }
        $lista = static fn(mixed $json): array => is_array($v = json_decode((string) $json, true)) ? array_values($v) : [];

        return $c
            ->setActiva((bool) $f["activa"])
            ->setNivel(NivelActividad::tryFrom((string) $f["nivel"]) ?? NivelActividad::MEDIO)
            ->setFamiliasApagadas(array_values(array_filter(array_map(static fn($t) => TipoActividad::tryFrom((string) $t), $lista($f["familias_apagadas"])))))
            ->setAlcance((string) $f["alcance"])
            ->setSeleccion(new Seleccion($lista($f["seleccion"])))
            ->setRetencionMeses((int) $f["retencion_meses"])
            ->setIntervaloPantallaSegundos((int) $f["intervalo_pantalla_segundos"])
            ->setMaxDetalleKb((int) $f["max_detalle_kb"])
            ->setCamposExtra(array_map("strval", $lista($f["campos_extra"])))
            ->setCambiosEntidades((bool) $f["cambios_entidades"]);
    }

    /** Tras guardar: la próxima petición vuelve a leer. */
    public function olvidar(): void
    {
        $this->politica = null;
    }
}
