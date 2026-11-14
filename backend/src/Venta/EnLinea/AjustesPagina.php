<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\ConfiguracionPagina;
use App\Entity\Usuario;
use App\Venta\EnLinea\Modificacion\Penalizacion;
use App\Venta\EnLinea\Modificacion\PoliticaModificacion;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Lee y guarda `ConfiguracionPagina` (ADR-023). Sin fila, valen los
 * valores por defecto (sin recargo, venta activa, cierre a 60 min).
 */
final class AjustesPagina
{
    /** Las reservas se liberan a más tardar este tiempo antes de salir: el cierre no puede ser menor. */
    public const CIERRE_MINIMO_MINUTOS = 45;
    public const CIERRE_MAXIMO_MINUTOS = 24 * 60;
    /** Límites de las modificaciones del cliente («Mis compras»). */
    public const MODIFICACIONES_MAXIMO = 20;
    public const MODIFICACION_MINIMO_MINUTOS = 0;
    public const MODIFICACION_MAXIMO_MINUTOS = 7 * 24 * 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    public function actual(): ConfiguracionPagina
    {
        return $this->em->find(ConfiguracionPagina::class, ConfiguracionPagina::ID) ?? new ConfiguracionPagina();
    }

    public function recargo(): Recargo
    {
        return Recargo::de($this->actual()->getRecargoPorciento());
    }

    /** Las reglas de las modificaciones del cliente con la configuración actual. */
    public function politicaModificacion(): PoliticaModificacion
    {
        return PoliticaModificacion::de($this->actual());
    }

    /**
     * `$modificaciones`: `{ porAsiento, minutos, penalizacion, penalizacionPorAsiento }`
     * (lo que falte queda como estaba).
     *
     * @param array<string, mixed>|null $modificaciones
     *
     * @throws VentaRechazada
     */
    public function guardar(string|int|float $recargoPorciento, bool $ventaEnLinea, int $cierreMinutos, ?Usuario $por, ?array $modificaciones = null): ConfiguracionPagina
    {
        if ($cierreMinutos < self::CIERRE_MINIMO_MINUTOS || $cierreMinutos > self::CIERRE_MAXIMO_MINUTOS) {
            throw new VentaRechazada(sprintf(
                "El cierre de la venta en línea debe estar entre %d y %d minutos antes de la salida.",
                self::CIERRE_MINIMO_MINUTOS,
                self::CIERRE_MAXIMO_MINUTOS,
            ), "cierre_invalido");
        }
        $config = $this->em->find(ConfiguracionPagina::class, ConfiguracionPagina::ID);
        if ($config === null) {
            $config = new ConfiguracionPagina();
            $this->em->persist($config);
        }
        if ($modificaciones !== null) {
            $porAsiento = (int) ($modificaciones["porAsiento"] ?? $config->getModificacionesPorAsiento());
            $minutos = (int) ($modificaciones["minutos"] ?? $config->getModificacionMinutos());
            if ($porAsiento < 0 || $porAsiento > self::MODIFICACIONES_MAXIMO) {
                throw new VentaRechazada(sprintf("Las modificaciones por asiento deben estar entre 0 y %d.", self::MODIFICACIONES_MAXIMO), "modificaciones_invalidas");
            }
            if ($minutos < self::MODIFICACION_MINIMO_MINUTOS || $minutos > self::MODIFICACION_MAXIMO_MINUTOS) {
                throw new VentaRechazada(sprintf(
                    "El tiempo mínimo para modificar debe estar entre %d y %d minutos antes de la salida.",
                    self::MODIFICACION_MINIMO_MINUTOS,
                    self::MODIFICACION_MAXIMO_MINUTOS,
                ), "modificacion_minutos_invalido");
            }
            $penalizacion = Penalizacion::de(
                $modificaciones["penalizacion"] ?? $config->getPenalizacion(),
                (bool) ($modificaciones["penalizacionPorAsiento"] ?? $config->isPenalizacionPorAsiento()),
            );
            $config->actualizarModificaciones($porAsiento, $minutos, $penalizacion->quetzales, $penalizacion->porAsiento);
        }
        $config->actualizar(Recargo::de($recargoPorciento)->porciento, $ventaEnLinea, $cierreMinutos, $por, $this->reloj->now());
        $this->em->flush();

        return $config;
    }
}
