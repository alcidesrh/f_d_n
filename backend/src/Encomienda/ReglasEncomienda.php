<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Encomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\TransicionEncomienda;

/**
 * Reglas puras de los movimientos de una encomienda (ADR-035). `rechazo()`
 * son los guards del workflow `encomienda` (ADR-038): lo que se sabe de la
 * encomienda misma. Lo demás depende de la operación y lo exige el servicio:
 * la salida en que se embarca, la estación donde se baja y quién opera. Los
 * enclaves van por id; `$estacionUsuario` null es un usuario de oficina (sin
 * estación), que puede operar en cualquiera.
 */
final class ReglasEncomienda
{
    /** Estados de salida en que se puede subir una encomienda. */
    private const SALIDAS_EMBARCABLES = [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA];

    /**
     * Por qué la encomienda no admite la transición (además de su estado, que
     * lo decide la máquina), o null si la admite.
     */
    public static function rechazo(TransicionEncomienda $t, Encomienda $e): ?EncomiendaRechazada
    {
        $ubicacion = $e->getUbicacion()?->getId();

        return match ($t) {
            TransicionEncomienda::EMBARCAR, TransicionEncomienda::EMBARCAR_EN_RUTA => match (true) {
                $ubicacion === null => new EncomiendaRechazada("La encomienda no está en la bodega de ninguna estación.", "estado"),
                $ubicacion === $e->getDestino()?->getId() => new EncomiendaRechazada("La encomienda ya está en su destino: se entrega.", "en_destino"),
                default => null,
            },
            TransicionEncomienda::PARTIR => $e->getSalida()?->getEstado() === EstadoSalida::INICIADA
                ? null
                : new EncomiendaRechazada(sprintf("La salida está %s: la encomienda parte cuando inicia su salida.", $e->getSalida()?->getEstado()->value ?? "sin asignar"), "salida_no_iniciada", 409),
            TransicionEncomienda::DESEMBARCAR => $e->getSalida() === null ? new EncomiendaRechazada("La encomienda no va en ninguna salida.", "estado") : null,
            TransicionEncomienda::ENTREGAR => $ubicacion !== null && $ubicacion === $e->getDestino()?->getId()
                ? null
                : new EncomiendaRechazada("Solo se entrega una encomienda desembarcada en su destino o en tránsito hacia él.", "estado"),
            TransicionEncomienda::ANULAR => match (true) {
                $e->getModalidad() === ModalidadEncomienda::CON_BOLETO => new EncomiendaRechazada("La encomienda va con un boleto: se anula al anular el boleto.", "con_boleto"),
                $ubicacion === null || $ubicacion !== $e->getOrigen()?->getId() => new EncomiendaRechazada("Solo se anula una encomienda que no ha viajado (en la bodega de su origen).", "estado"),
                default => null,
            },
            TransicionEncomienda::ANULAR_CON_BOLETO => $e->getModalidad() === ModalidadEncomienda::CON_BOLETO
                ? null
                : new EncomiendaRechazada("Solo la encomienda que va con un boleto se anula con su venta.", "sin_boleto"),
        };
    }

    /**
     * La salida en que se embarca: que esté abierta, sea de su empresa y siga
     * después de la estación donde está la encomienda.
     *
     * @param list<int> $paradas enclaves de la ruta de la salida, en orden
     *
     * @throws EncomiendaRechazada
     */
    public static function exigirSalidaParaEmbarcar(int $ubicacion, EstadoSalida $estadoSalida, array $paradas, ?int $empresaEncomienda, ?int $empresaSalida): void
    {
        if (!in_array($estadoSalida, self::SALIDAS_EMBARCABLES, true)) {
            throw new EncomiendaRechazada("La salida ya terminó o está anulada: no se le suben encomiendas.", "salida_cerrada");
        }
        if ($empresaEncomienda !== $empresaSalida) {
            throw new EncomiendaRechazada("La encomienda es de otra empresa: solo viaja en salidas de su empresa.", "otra_empresa");
        }
        $posicion = array_search($ubicacion, $paradas, true);
        if ($posicion === false || $posicion === count($paradas) - 1) {
            throw new EncomiendaRechazada("La ruta de la salida no sale de la estación donde está la encomienda.", "fuera_de_ruta");
        }
    }

    /**
     * Se desembarca en una parada de su salida.
     *
     * @param list<int> $paradas enclaves de la ruta de su salida
     *
     * @throws EncomiendaRechazada
     */
    public static function exigirParadaDeSalida(int $estacion, array $paradas): void
    {
        if (!in_array($estacion, $paradas, true)) {
            throw new EncomiendaRechazada("La salida de la encomienda no pasa por esa estación.", "fuera_de_ruta");
        }
    }

    /**
     * Quién opera: un usuario de esa estación (la bodega donde está, su
     * destino al entregar, su origen al anular) o de oficina.
     *
     * @throws EncomiendaRechazada
     */
    public static function exigirEstacion(?int $estacionUsuario, ?int $estacion, string $mensaje): void
    {
        if ($estacionUsuario !== null && $estacionUsuario !== $estacion) {
            throw new EncomiendaRechazada($mensaje, "otra_estacion", 403);
        }
    }

    /**
     * La salida lleva la encomienda hasta su destino (si no, hará transbordo).
     *
     * @param list<int> $paradas
     */
    public static function llegaADestino(array $paradas, int $desde, int $destino): bool
    {
        $i = array_search($desde, $paradas, true);
        $j = array_search($destino, $paradas, true);

        return $i !== false && $j !== false && $j > $i;
    }
}
