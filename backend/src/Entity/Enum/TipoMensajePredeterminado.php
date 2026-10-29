<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Qué manda una instancia de mensaje predeterminado (ADR-045) y cuándo.
 * Catálogo cerrado, como las tareas del programador: lo que se configura es
 * la instancia (nombre, audiencia, contenido, empresa, ruta, hora), no el tipo.
 *
 * - `libre`: solo el contenido de la instancia (texto, imagen, archivos), a
 *   su hora en los días elegidos.
 * - `salida_iniciada`: al pasar la hora de cada salida de la ruta y empresa elegidas.
 * - `anomalia_*`: la revisión de cada madrugada, cuando encuentra algo.
 * - `ventas_dia`, `desempeno_dia`: a su hora (al cierre), lo del mismo día de
 *   la empresa elegida.
 */
enum TipoMensajePredeterminado: string
{
    case LIBRE = "libre";
    case SALIDA_INICIADA = "salida_iniciada";
    case ANOMALIA_REASIGNACION = "anomalia_reasignacion";
    case ANOMALIA_TARIFA = "anomalia_tarifa";
    case VENTAS_DIA = "ventas_dia";
    case DESEMPENO_DIA = "desempeno_dia";

    /** La hora de los reportes del día si no se elige otra. */
    public const HORA_CIERRE = "22:00";

    public function nombre(): string
    {
        return match ($this) {
            self::LIBRE => "Mensaje con horario",
            self::SALIDA_INICIADA => "Salida iniciada",
            self::ANOMALIA_REASIGNACION => "Anomalía en reasignación",
            self::ANOMALIA_TARIFA => "Anomalía en tarifa",
            self::VENTAS_DIA => "Ventas del día",
            self::DESEMPENO_DIA => "Desempeño general del día",
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::LIBRE => "El texto, la imagen o los archivos de la instancia, a su hora en los días elegidos.",
            self::SALIDA_INICIADA => "Al pasar la hora de cada salida de la ruta: hora de inicio, asientos libres y ocupados por canal y la tabla por vendedor (PDF).",
            self::ANOMALIA_REASIGNACION => "Cada madrugada: los boletos del día anterior reasignados más de dos veces, con su bitácora.",
            self::ANOMALIA_TARIFA => "Cada madrugada: las tarifas creadas, editadas o eliminadas el día anterior, con su bitácora.",
            self::VENTAS_DIA => "Al cierre: totales del día de la empresa y desglose por estación.",
            self::DESEMPENO_DIA => "Al cierre: ingresos, boletos, ocupación, anulaciones, cortesías y lo que pide acción, del mismo día.",
        };
    }

    public function exigeEmpresa(): bool
    {
        return in_array($this, [self::SALIDA_INICIADA, self::VENTAS_DIA, self::DESEMPENO_DIA], true);
    }

    public function exigeRuta(): bool
    {
        return $this === self::SALIDA_INICIADA;
    }

    /** Sale a una hora del día (los demás, cuando ocurre lo que vigilan). */
    public function conHora(): bool
    {
        return in_array($this, [self::LIBRE, self::VENTAS_DIA, self::DESEMPENO_DIA], true);
    }

    /** Elige los días de la semana (los reportes salen todos los días). */
    public function conDias(): bool
    {
        return $this === self::LIBRE;
    }

    /** Sin predeterminado: el contenido propio es todo el mensaje y es obligatorio. */
    public function exigeContenido(): bool
    {
        return $this === self::LIBRE;
    }

    public function esAnomalia(): bool
    {
        return $this === self::ANOMALIA_REASIGNACION || $this === self::ANOMALIA_TARIFA;
    }
}
