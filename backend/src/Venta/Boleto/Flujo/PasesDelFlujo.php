<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Flujo;

use App\Acceso\Capacidad;
use App\Autorizacion\Autorizable;
use App\Autorizacion\DispensasEnCurso;
use App\Autorizacion\Pases;
use App\Entity\BoletoAsiento;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Salida\SalidaRechazada;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\ReglasBoletos;
use Psr\Clock\ClockInterface;

/**
 * Lo que el flujo del boleto exige al anular o reasignar, y las
 * autorizaciones que lo salvan (lo usan `AnulacionBoletos` y
 * `ReasignacionBoletos`): al boleto que solo detiene la hora de su salida lo
 * cubre una fuera de hora (ADR-040); al que detiene su estado o el de su
 * salida, pero el rol de sistema sí operaría, una fuera de estado (ADR-058).
 * Lo demás se rechaza como siempre.
 */
final class PasesDelFlujo
{
    public function __construct(
        private readonly FlujoBoleto $flujo,
        private readonly Pases $pases,
        private readonly DispensasEnCurso $dispensas,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Las dispensas valen toda la petición (los guards las consultan al
     * aplicar la transición); los pases se usan después, en la transacción
     * de quien opera.
     *
     * @param list<BoletoAsiento> $boletos
     *
     * @return array{pases: list<SolicitudAutorizacion>, fueraDeEstado: list<int>} las autorizaciones a usar y los boletos que se operan fuera de estado
     *
     * @throws VentaRechazada|SalidaRechazada
     */
    public function exigir(array $boletos, TransicionBoleto $t, Capacidad $capacidad, Usuario $usuario): array
    {
        $ahora = $this->reloj->now();
        /** @var array<string, array{ids: list<int>, rechazo: VentaRechazada|SalidaRechazada}> $porDispensa */
        $porDispensa = [];
        foreach ($boletos as $b) {
            $rechazo = $this->flujo->rechazo($b, $t);
            if ($rechazo === null) {
                continue;
            }
            $dispensa = match (true) {
                ReglasBoletos::soloPorHora($b, $ahora) => Dispensa::HORA,
                ReglasBoletos::soloPorEstado($b, $ahora) => Dispensa::ESTADO,
                default => throw $rechazo,
            };
            $porDispensa[$dispensa->value]["ids"][] = (int) $b->getId();
            $porDispensa[$dispensa->value]["rechazo"] ??= $rechazo;
        }
        if ($porDispensa === []) {
            return ["pases" => [], "fueraDeEstado" => []];
        }

        // Con las dispensas, que no lo detenga otra cosa (antes de pedir la autorización).
        $fueraDeEstado = $porDispensa[Dispensa::ESTADO->value]["ids"] ?? [];
        $this->dispensas->permitirFueraDeHora(...($porDispensa[Dispensa::HORA->value]["ids"] ?? []));
        $this->dispensas->permitirFueraDeEstado(...$fueraDeEstado);
        foreach ($boletos as $b) {
            $this->flujo->exigir($b, $t);
        }

        $pases = [];
        foreach ($porDispensa as $dispensa => ["ids" => $ids, "rechazo" => $rechazo]) {
            $pases[] = $this->pases->exigir($usuario, Autorizable::boletos($capacidad, Dispensa::from($dispensa), $ids), $rechazo->getMessage(), $rechazo->codigo, 409);
        }

        return ["pases" => $pases, "fueraDeEstado" => $fueraDeEstado];
    }
}
