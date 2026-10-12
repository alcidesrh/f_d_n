<?php

declare(strict_types=1);

namespace App\Salida\Abordaje;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoSalida;
use App\Salida\SalidaRechazada;

/**
 * Reglas puras del abordaje (ADR-032), las del legado ("Chequear boletos por
 * salida"):
 *
 * - Una salida se pone a abordar y se inicia desde su estación de origen
 *   (un usuario sin estación, de oficina, puede hacerlo con cualquiera).
 * - Se chequea mientras la salida aborda o ya inició, desde 2 horas antes de
 *   la hora de salida hasta 30 después; cada boleto lo chequea la estación
 *   donde sube el pasajero (o un usuario sin estación).
 * - Chequear con la salida ya iniciada (sube en una parada intermedia) lo
 *   deja directo en tránsito.
 * - Al iniciar, los chequeados pasan a tránsito y los emitidos que subían en
 *   el origen quedan cancelados (no se presentaron).
 * - La salida iniciada la finaliza su estación de destino (o un usuario sin
 *   estación): los boletos en tránsito quedan finalizados y los emitidos que
 *   nadie chequeó, cancelados.
 */
final class ReglasAbordaje
{
    public const HORAS_ANTES = 2;
    public const HORAS_DESPUES = 30;

    /** Ventana del selector de salidas: desde 24 h atrás hasta 6 h adelante. */
    public const SELECTOR_HORAS_ATRAS = 24;
    public const SELECTOR_HORAS_ADELANTE = 6;

    /** Estados en que se chequean boletos. */
    public const CHEQUEABLE = [EstadoSalida::ABORDANDO, EstadoSalida::INICIADA];

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} desde/hasta del selector de salidas */
    public static function ventanaSelector(\DateTimeImmutable $ahora): array
    {
        return [
            $ahora->modify(sprintf("-%d hours", self::SELECTOR_HORAS_ATRAS)),
            $ahora->modify(sprintf("+%d hours", self::SELECTOR_HORAS_ADELANTE)),
        ];
    }

    /**
     * Qué salidas ve un usuario en el selector: sin estación, todas las
     * programadas, abordando o iniciadas; con estación, las que salen de ella
     * (programadas o abordando) y las iniciadas que pasan o llegan a ella.
     *
     * @param list<int> $enclavesDespues paradas de la ruta después del origen
     */
    public static function visibleEnSelector(EstadoSalida $estado, ?int $estacionUsuario, int $origen, array $enclavesDespues): bool
    {
        if ($estacionUsuario === null) {
            return in_array($estado, [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA], true);
        }
        if ($estacionUsuario === $origen) {
            return in_array($estado, [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO], true);
        }

        return $estado === EstadoSalida::INICIADA && in_array($estacionUsuario, $enclavesDespues, true);
    }

    /** @throws SalidaRechazada */
    public static function exigirDesdeElOrigen(?int $estacionUsuario, int $origen, string $origenNombre, string $operacion): void
    {
        if ($estacionUsuario !== null && $estacionUsuario !== $origen) {
            throw new SalidaRechazada(
                sprintf("Solo un usuario de la estación %s (origen de la ruta) o de oficina puede %s.", $origenNombre, $operacion),
                "estacion_no_origen",
                403,
            );
        }
    }

    /** @throws SalidaRechazada */
    public static function exigirDesdeElDestino(?int $estacionUsuario, int $destino, string $destinoNombre, string $operacion): void
    {
        if ($estacionUsuario !== null && $estacionUsuario !== $destino) {
            throw new SalidaRechazada(
                sprintf("Solo un usuario de la estación %s (destino de la ruta) o de oficina puede %s.", $destinoNombre, $operacion),
                "estacion_no_destino",
                403,
            );
        }
    }

    /**
     * Abordar: la salida programada, sin adelantarse a la ventana de chequeo
     * (abordando ya no se edita ni se anula).
     *
     * @throws SalidaRechazada
     */
    public static function exigirAbordable(EstadoSalida $estado, \DateTimeInterface $fecha, \DateTimeImmutable $ahora): void
    {
        if ($estado !== EstadoSalida::PROGRAMADA) {
            throw new SalidaRechazada(sprintf("La salida está %s: solo se pone a abordar una salida programada.", $estado->value), "salida_no_programada", 409);
        }
        self::exigirNoAntesDeLaVentana($fecha, $ahora, "se pone a abordar");
    }

    /** @throws SalidaRechazada */
    public static function exigirIniciable(EstadoSalida $estado): void
    {
        if ($estado !== EstadoSalida::ABORDANDO) {
            throw new SalidaRechazada(sprintf("La salida está %s: solo se inicia una salida que está abordando.", $estado->value), "salida_no_abordando", 409);
        }
    }

    /** @throws SalidaRechazada */
    public static function exigirFinalizable(EstadoSalida $estado): void
    {
        if ($estado !== EstadoSalida::INICIADA) {
            throw new SalidaRechazada(sprintf("La salida está %s: solo se finaliza una salida iniciada.", $estado->value), "salida_no_iniciada", 409);
        }
    }

    /** @throws SalidaRechazada */
    public static function exigirChequeable(EstadoSalida $estado, \DateTimeInterface $fecha, \DateTimeImmutable $ahora): void
    {
        if (!in_array($estado, self::CHEQUEABLE, true)) {
            throw new SalidaRechazada(sprintf("La salida está %s: los boletos se chequean cuando la salida está abordando o iniciada.", $estado->value), "salida_no_abordando", 409);
        }
        self::exigirNoAntesDeLaVentana($fecha, $ahora, "se empiezan a chequear los boletos");
        if ($ahora > \DateTimeImmutable::createFromInterface($fecha)->modify(sprintf("+%d hours", self::HORAS_DESPUES))) {
            throw new SalidaRechazada(sprintf("Los boletos solo se chequean hasta %d horas después de la hora de salida.", self::HORAS_DESPUES), "fuera_de_ventana", 409);
        }
    }

    /** Estados de la salida en que se cancela un boleto desde el listado (como el legado). */
    public const CANCELABLE = [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA];

    /**
     * Cancelar un boleto desde el listado ("no se presentó", como el legado):
     * emitido o chequeado, con la salida programada, abordando o iniciada.
     *
     * @throws SalidaRechazada
     */
    public static function exigirCancelable(EstadoBoletoAsiento $boleto, EstadoSalida $salida): void
    {
        if (!in_array($boleto, [EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO], true)) {
            throw new SalidaRechazada(sprintf("El boleto está %s: solo se cancela un boleto emitido o chequeado.", $boleto->value), "boleto_no_cancelable", 409);
        }
        if (!in_array($salida, self::CANCELABLE, true)) {
            throw new SalidaRechazada(sprintf("La salida está %s: se cancela un boleto con la salida programada, abordando o iniciada.", $salida->value), "salida_no_cancelable", 409);
        }
    }

    /**
     * Revertir un cancelado (el pasajero sí viaja, como el "Revertir" del
     * legado): vuelve a emitido con la salida programada, abordando o
     * iniciada. Las cortesías y lo vendido por agencia no se revierten (el
     * legado tampoco). Que el asiento siga libre lo comprueba `Abordaje`.
     *
     * @throws SalidaRechazada
     */
    public static function exigirRevertible(EstadoBoletoAsiento $boleto, EstadoSalida $salida, bool $cortesia, bool $agencia): void
    {
        if ($boleto !== EstadoBoletoAsiento::CANCELADO) {
            throw new SalidaRechazada(sprintf("El boleto está %s: solo se revierte un boleto cancelado.", $boleto->value), "boleto_no_cancelado", 409);
        }
        if (!in_array($salida, self::CANCELABLE, true)) {
            throw new SalidaRechazada(sprintf("La salida está %s: se revierte un boleto con la salida programada, abordando o iniciada.", $salida->value), "salida_no_cancelable", 409);
        }
        if ($cortesia) {
            throw new SalidaRechazada("Las cortesías canceladas no se revierten.", "cortesia", 409);
        }
        if ($agencia) {
            throw new SalidaRechazada("Los boletos de agencia cancelados no se revierten.", "agencia", 409);
        }
    }

    /** Si la estación del usuario puede chequear un boleto que sube en `$sube`. */
    public static function puedeChequearEn(?int $estacionUsuario, int $sube): bool
    {
        return $estacionUsuario === null || $estacionUsuario === $sube;
    }

    /** El estado en que queda un boleto chequeado. */
    public static function estadoAlChequear(EstadoSalida $salida): EstadoBoletoAsiento
    {
        return $salida === EstadoSalida::INICIADA ? EstadoBoletoAsiento::TRANSITO : EstadoBoletoAsiento::CHEQUEADO;
    }

    /**
     * Al iniciar la salida: el chequeado pasa a tránsito; el emitido que subía
     * en el origen, a cancelado; los demás (suben más adelante, o ya no
     * ocupan) no cambian.
     */
    public static function estadoAlIniciar(EstadoBoletoAsiento $estado, int $ordenSube): ?EstadoBoletoAsiento
    {
        return match (true) {
            $estado === EstadoBoletoAsiento::CHEQUEADO => EstadoBoletoAsiento::TRANSITO,
            $estado === EstadoBoletoAsiento::EMITIDO && $ordenSube === 0 => EstadoBoletoAsiento::CANCELADO,
            default => null,
        };
    }

    /**
     * Al finalizar la salida, los pasos de cada boleto vivo (uno por
     * transición, para la bitácora): tránsito → finalizado; chequeado (de
     * una salida migrada que no pasó por "iniciar") → tránsito → finalizado;
     * emitido (nadie lo chequeó) → cancelado.
     *
     * @return list<EstadoBoletoAsiento>
     */
    public static function pasosAlFinalizar(EstadoBoletoAsiento $estado): array
    {
        return match ($estado) {
            EstadoBoletoAsiento::TRANSITO => [EstadoBoletoAsiento::FINALIZADO],
            EstadoBoletoAsiento::CHEQUEADO => [EstadoBoletoAsiento::TRANSITO, EstadoBoletoAsiento::FINALIZADO],
            EstadoBoletoAsiento::EMITIDO => [EstadoBoletoAsiento::CANCELADO],
            default => [],
        };
    }

    /**
     * Las listas de chequeados y no presentados: ids enteros, sin repetir y
     * sin un mismo boleto en las dos.
     *
     * @param list<mixed> $si
     * @param list<mixed> $no
     *
     * @return array{0: list<int>, 1: list<int>}
     *
     * @throws SalidaRechazada
     */
    public static function seleccion(array $si, array $no): array
    {
        $limpiar = static fn(array $ids) => array_values(array_unique(array_map("intval", array_filter($ids, "is_numeric"))));
        [$si, $no] = [$limpiar($si), $limpiar($no)];
        if ($si === [] && $no === []) {
            throw new SalidaRechazada("Marque al menos un boleto.", "sin_boletos");
        }
        if (array_intersect($si, $no) !== []) {
            throw new SalidaRechazada("Un boleto no puede estar chequeado y no presentado a la vez.", "seleccion_invalida");
        }
        if (count($si) + count($no) > 200) {
            throw new SalidaRechazada("Demasiados boletos en un solo chequeo.", "seleccion_invalida");
        }

        return [$si, $no];
    }

    private static function exigirNoAntesDeLaVentana(\DateTimeInterface $fecha, \DateTimeImmutable $ahora, string $que): void
    {
        if ($ahora < \DateTimeImmutable::createFromInterface($fecha)->modify(sprintf("-%d hours", self::HORAS_ANTES))) {
            throw new SalidaRechazada(sprintf("Faltan más de %d horas para la salida: %s desde %d horas antes.", self::HORAS_ANTES, $que, self::HORAS_ANTES), "fuera_de_ventana", 409);
        }
    }
}
