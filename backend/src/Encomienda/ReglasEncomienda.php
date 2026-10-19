<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;

/**
 * Reglas puras de los movimientos de una encomienda (ADR-035). Los enclaves
 * van por id; `$estacionUsuario` null es un usuario de oficina (sin estación),
 * que puede operar en cualquiera.
 */
final class ReglasEncomienda
{
    /** Estados de salida en que se puede subir una encomienda. */
    private const SALIDAS_EMBARCABLES = [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA];

    /**
     * Estado en que queda al embarcarla en esa salida: en tránsito si la salida ya inició.
     *
     * @param list<int> $paradas enclaves de la ruta de la salida, en orden
     *
     * @throws EncomiendaRechazada
     */
    public static function alEmbarcar(
        EstadoEncomienda $estado,
        ?int $ubicacion,
        ?int $estacionUsuario,
        EstadoSalida $estadoSalida,
        array $paradas,
        ?int $empresaEncomienda,
        ?int $empresaSalida,
    ): EstadoEncomienda {
        if (!$estado->enBodega() || $ubicacion === null) {
            throw new EncomiendaRechazada(sprintf("Solo se embarca una encomienda en bodega (recibida o desembarcada); esta está %s.", mb_strtolower($estado->etiqueta())), "estado");
        }
        if ($estacionUsuario !== null && $estacionUsuario !== $ubicacion) {
            throw new EncomiendaRechazada("La encomienda está en la bodega de otra estación: la embarca un usuario de esa estación.", "otra_estacion", 403);
        }
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

        return $estadoSalida === EstadoSalida::INICIADA ? EstadoEncomienda::TRANSITO : EstadoEncomienda::EMBARCADA;
    }

    /**
     * @param list<int> $paradas enclaves de la ruta de su salida
     *
     * @throws EncomiendaRechazada
     */
    public static function exigirDesembarcable(EstadoEncomienda $estado, int $estacion, array $paradas): void
    {
        if (!$estado->aBordo()) {
            throw new EncomiendaRechazada(sprintf("Solo se desembarca una encomienda embarcada o en tránsito; esta está %s.", mb_strtolower($estado->etiqueta())), "estado");
        }
        if (!in_array($estacion, $paradas, true)) {
            throw new EncomiendaRechazada("La salida de la encomienda no pasa por esa estación.", "fuera_de_ruta");
        }
    }

    /** @throws EncomiendaRechazada */
    public static function exigirEntregable(EstadoEncomienda $estado, ?int $ubicacion, int $destino, ?int $estacionUsuario): void
    {
        if ($estado !== EstadoEncomienda::DESEMBARCADA || $ubicacion !== $destino) {
            throw new EncomiendaRechazada("Solo se entrega una encomienda desembarcada en su destino.", "estado");
        }
        if ($estacionUsuario !== null && $estacionUsuario !== $destino) {
            throw new EncomiendaRechazada("La entrega un usuario de la estación de destino.", "otra_estacion", 403);
        }
    }

    /**
     * Se anula mientras no haya viajado: en bodega en su origen. La que va con
     * boleto se anula con su venta.
     *
     * @throws EncomiendaRechazada
     */
    public static function exigirAnulable(EstadoEncomienda $estado, ModalidadEncomienda $modalidad, ?int $ubicacion, int $origen, ?int $estacionUsuario): void
    {
        if ($modalidad === ModalidadEncomienda::CON_BOLETO) {
            throw new EncomiendaRechazada("La encomienda va con un boleto: se anula al anular el boleto.", "con_boleto");
        }
        if (!$estado->enBodega() || $ubicacion !== $origen) {
            throw new EncomiendaRechazada("Solo se anula una encomienda que no ha viajado (en la bodega de su origen).", "estado");
        }
        if ($estacionUsuario !== null && $estacionUsuario !== $origen) {
            throw new EncomiendaRechazada("La anula un usuario de la estación de origen.", "otra_estacion", 403);
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
