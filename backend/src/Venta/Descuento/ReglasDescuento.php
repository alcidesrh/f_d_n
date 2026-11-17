<?php

declare(strict_types=1);

namespace App\Venta\Descuento;

use Money\Money;

/**
 * Qué descuento lleva un asiento (ADR-066). Puro.
 *
 * - Una promoción vale para una salida si está activa, la fecha de la salida
 *   cae en su vigencia y coinciden la empresa, la ruta y la clase de bus que
 *   fije. Sin código se aplica sola; con código, solo si se escribió ese.
 * - Los descuentos no se suman: el asiento lleva el porcentaje más alto entre
 *   su tipo de pasajero y las promociones que valen. Empatados, el tipo de
 *   pasajero (es lo que la taquilla eligió).
 * - El importe se redondea al centavo, a favor del pasajero en el medio.
 */
final class ReglasDescuento
{
    /**
     * @param array{activa: bool, codigo: ?string, desde: string, hasta: string, empresa: ?int, ruta: ?int, busClase: ?int} $promocion fechas `AAAA-MM-DD`
     * @param array{fecha: string, empresa: ?int, ruta: ?int, busClase: ?int}                                            $salida    fecha `AAAA-MM-DD`
     */
    public static function vale(array $promocion, array $salida, ?string $codigo): bool
    {
        return $promocion["activa"]
            && $salida["fecha"] >= $promocion["desde"]
            && $salida["fecha"] <= $promocion["hasta"]
            && ($promocion["empresa"] === null || $promocion["empresa"] === $salida["empresa"])
            && ($promocion["ruta"] === null || $promocion["ruta"] === $salida["ruta"])
            && ($promocion["busClase"] === null || $promocion["busClase"] === $salida["busClase"])
            && ($promocion["codigo"] === null || $promocion["codigo"] === self::codigo($codigo));
    }

    /**
     * El que gana entre el tipo de pasajero y las promociones; null si no hay ninguno.
     *
     * @param ?array{id: int, nombre: string, porciento: int}  $tipo
     * @param list<array{id: int, nombre: string, porciento: int}> $promociones las que valen
     */
    public static function elegir(?array $tipo, array $promociones): ?Descuento
    {
        $mejor = $tipo === null ? null : new Descuento($tipo["porciento"], self::motivo($tipo["nombre"], $tipo["porciento"]), $tipo["id"]);
        foreach ($promociones as $p) {
            if ($mejor === null || $p["porciento"] > $mejor->porciento) {
                $mejor = new Descuento($p["porciento"], self::motivo($p["nombre"], $p["porciento"]), $tipo["id"] ?? null, $p["id"]);
            }
        }

        return $mejor !== null && $mejor->porciento > 0 ? $mejor : null;
    }

    /** Cuánto se descuenta de `$precio`: el porcentaje, redondeado al centavo (la mitad, a favor del pasajero). */
    public static function importe(Money $precio, int $porciento): Money
    {
        $porciento = max(0, min(100, $porciento));
        $centavos = intdiv((int) $precio->getAmount() * $porciento * 2 + 100, 200);

        return new Money(min($centavos, (int) $precio->getAmount()), $precio->getCurrency());
    }

    /** Como se guarda y se compara: sin espacios, en mayúsculas; vacío = null. */
    public static function codigo(?string $codigo): ?string
    {
        $codigo = $codigo === null ? "" : mb_strtoupper(trim($codigo));

        return $codigo === "" ? null : $codigo;
    }

    public static function motivo(string $nombre, int $porciento): string
    {
        return mb_substr(sprintf("%s %d %%", $nombre, $porciento), 0, 100);
    }
}
