<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Datos de la tarjeta tal como llegan del formulario de pago. Solo viven en
 * memoria durante la petición que los pasa a la pasarela: nunca se guardan,
 * se registran en logs ni se serializan (`__debugInfo`/`__serialize`).
 */
final class Tarjeta
{
    private function __construct(
        private readonly string $numero,
        public readonly int $mesExpira,
        public readonly int $anioExpira,
        private readonly string $cvv,
        public readonly string $titular,
    ) {}

    /**
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada si algún dato es inválido
     */
    public static function desdeArray(array $datos, \DateTimeImmutable $hoy): self
    {
        $numero = preg_replace('/\D+/', '', (string) ($datos["numero"] ?? "")) ?? "";
        $cvv = preg_replace('/\D+/', '', (string) ($datos["cvv"] ?? "")) ?? "";
        $titular = trim((string) ($datos["titular"] ?? ""));
        [$mes, $anio] = self::expiracion($datos);

        if (strlen($numero) < 13 || strlen($numero) > 19 || !self::luhn($numero)) {
            throw new VentaRechazada("El número de tarjeta no es válido.", "tarjeta_invalida");
        }
        if (self::marca($numero) === null) {
            throw new VentaRechazada("Solo se aceptan tarjetas Visa y Mastercard.", "tarjeta_marca");
        }
        if ($mes < 1 || $mes > 12 || $anio * 100 + $mes < (int) $hoy->format("Ym")) {
            throw new VentaRechazada("La tarjeta está vencida o la fecha de vencimiento no es válida.", "tarjeta_vencida");
        }
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            throw new VentaRechazada("El código de seguridad (CVV) no es válido.", "tarjeta_cvv");
        }
        if ($titular === "") {
            throw new VentaRechazada("Indique el nombre del titular de la tarjeta.", "tarjeta_titular");
        }

        return new self($numero, $mes, $anio, $cvv, mb_substr($titular, 0, 100));
    }

    public static function luhn(string $numero): bool
    {
        $suma = 0;
        $doble = false;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $d = (int) $numero[$i];
            if ($doble) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $suma += $d;
            $doble = !$doble;
        }

        return $suma % 10 === 0;
    }

    /** `visa`, `mastercard` o null. */
    public static function marca(string $numero): ?string
    {
        return match (true) {
            str_starts_with($numero, "4") => "visa",
            (bool) preg_match('/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $numero) => "mastercard",
            default => null,
        };
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function cvv(): string
    {
        return $this->cvv;
    }

    public function ultimos4(): string
    {
        return substr($this->numero, -4);
    }

    public function marcaTarjeta(): string
    {
        return (string) self::marca($this->numero);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ["tarjeta" => sprintf("%s ****%s", $this->marcaTarjeta(), $this->ultimos4())];
    }

    public function __serialize(): array
    {
        throw new \LogicException("Los datos de tarjeta no se serializan.");
    }

    /** @return array{0: int, 1: int} */
    private static function expiracion(array $datos): array
    {
        if (isset($datos["expira"])) {
            // "MM/AA" o "MM/AAAA"
            if (preg_match('#^\s*(\d{1,2})\s*/\s*(\d{2}|\d{4})\s*$#', (string) $datos["expira"], $m)) {
                $anio = (int) $m[2];

                return [(int) $m[1], $anio < 100 ? 2000 + $anio : $anio];
            }

            return [0, 0];
        }
        $anio = (int) ($datos["anio"] ?? 0);

        return [(int) ($datos["mes"] ?? 0), $anio < 100 ? 2000 + $anio : $anio];
    }
}
