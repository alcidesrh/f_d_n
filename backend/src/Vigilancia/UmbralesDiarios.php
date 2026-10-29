<?php

declare(strict_types=1);

namespace App\Vigilancia;

use App\Acceso\Autorizacion;
use App\Acceso\Limite;
use App\Acceso\Ubicacion;
use App\Entity\Usuario;
use App\Venta\Transaccion;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Los límites de aviso de los roles (ADR-044): después de anular, reasignar
 * o emitir cortesías, el servicio que operó le dice aquí cuántas llevaba el
 * usuario antes y cuántas lleva ahora. Si con esta operación llegó al número
 * de aviso de su rol (ahí donde operó), se emite `UmbralDiarioAlcanzado`
 * después del commit. No detiene nada: los que bloquean siguen en cada
 * servicio con su autorización (ADR-040).
 */
final class UmbralesDiarios
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly Transaccion $transaccion,
        private readonly EventDispatcherInterface $eventos,
    ) {}

    /** @param list<array{tipo: string, id: int}> $tarjetas */
    public function trasOperar(Usuario $usuario, Limite $aviso, int $antes, int $despues, Ubicacion $donde, array $tarjetas = []): void
    {
        if ($aviso->bloquea() || $despues <= $antes) {
            return;
        }
        $umbral = $this->autorizacion->de($usuario)->limite($aviso->capacidad()->value, $aviso, $donde);
        if (!self::cruza($umbral, $antes, $despues)) {
            return;
        }
        $evento = new UmbralDiarioAlcanzado((int) $usuario->getId(), $aviso->value, (int) $umbral, $despues, $donde->toArray(), array_slice($tarjetas, 0, 20));
        $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch($evento));
    }

    /** ¿Pasar de `$antes` a `$despues` llega al umbral? Solo la operación que lo alcanza avisa. */
    public static function cruza(?int $umbral, int $antes, int $despues): bool
    {
        return $umbral !== null && $umbral > 0 && $antes < $umbral && $despues >= $umbral;
    }
}
