<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Entity\Enum\PrecisionHora;

/**
 * Horario de una salida en las paradas de su ruta (puro, ADR-030): la hora
 * de la salida más los minutos hasta cada parada. Sin minutos, la hora del
 * origen (precisión desconocida). Una parada nunca queda antes que la
 * anterior: si los minutos del legado retroceden, se iguala a la anterior.
 */
final class Horario
{
    /**
     * @param list<array{orden: int, enclave: int, minutos: ?int, subir: bool, bajar: bool}> $paradas en orden
     *
     * @return list<array{orden: int, enclave: int, hora: \DateTimeImmutable, precision: PrecisionHora, subir: bool, bajar: bool}>
     */
    public static function calcular(\DateTimeImmutable $salida, array $paradas): array
    {
        $horario = [];
        $anterior = $salida;
        foreach ($paradas as $i => $p) {
            [$hora, $precision] = match (true) {
                $i === 0 => [$salida, PrecisionHora::EXACTA],
                $p["minutos"] !== null => [$salida->modify("+{$p["minutos"]} minutes"), PrecisionHora::ESTIMADA],
                default => [$salida, PrecisionHora::DESCONOCIDA],
            };
            if ($precision === PrecisionHora::ESTIMADA && $hora < $anterior) {
                $hora = $anterior;
            }
            if ($precision !== PrecisionHora::DESCONOCIDA) {
                $anterior = $hora;
            }
            $horario[] = [
                "orden" => $p["orden"],
                "enclave" => $p["enclave"],
                "hora" => $hora,
                "precision" => $precision,
                "subir" => $p["subir"],
                "bajar" => $p["bajar"],
            ];
        }

        return $horario;
    }
}
