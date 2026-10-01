<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\ConfiguracionPagina;
use App\Entity\Usuario;
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

    /** @throws VentaRechazada */
    public function guardar(string|int|float $recargoPorciento, bool $ventaEnLinea, int $cierreMinutos, ?Usuario $por): ConfiguracionPagina
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
        $config->actualizar(Recargo::de($recargoPorciento)->porciento, $ventaEnLinea, $cierreMinutos, $por, $this->reloj->now());
        $this->em->flush();

        return $config;
    }
}
