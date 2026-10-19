<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Enum\TipoArticuloEncomienda;

/** Un artículo pedido al cotizar o registrar una encomienda. */
final readonly class ArticuloSolicitado
{
    public function __construct(
        public TipoArticuloEncomienda $tipo,
        public int $cantidad,
        /** Libras por bulto (paquete). */
        public ?float $peso = null,
        /** `EncomiendaEspecial` (especial). */
        public ?int $especialId = null,
        public ?string $descripcion = null,
        public ?int $alto = null,
        public ?int $ancho = null,
        public ?int $largo = null,
    ) {}

    /**
     * @param array<string, mixed> $datos
     *
     * @throws EncomiendaRechazada
     */
    public static function desdeArray(array $datos, int $i = 0): self
    {
        $n = $i + 1;
        $tipo = TipoArticuloEncomienda::tryFrom((string) ($datos["tipo"] ?? ""))
            ?? throw new EncomiendaRechazada("Artículo {$n}: elija si es un paquete o un especial.");
        $cantidad = filter_var($datos["cantidad"] ?? 1, FILTER_VALIDATE_INT);
        if ($cantidad === false || $cantidad < 1 || $cantidad > Cotizador::MAX_CANTIDAD) {
            throw new EncomiendaRechazada(sprintf("Artículo %d: la cantidad va de 1 a %d.", $n, Cotizador::MAX_CANTIDAD));
        }
        $medida = static function (mixed $v, string $campo) use ($n): ?int {
            if ($v === null || $v === "") {
                return null;
            }
            $v = filter_var($v, FILTER_VALIDATE_INT);
            if ($v === false || $v < 1 || $v > 1000) {
                throw new EncomiendaRechazada("Artículo {$n}: el {$campo} va de 1 a 1000 cm.");
            }

            return $v;
        };
        $descripcion = trim((string) ($datos["descripcion"] ?? ""));

        $peso = null;
        $especial = null;
        if ($tipo === TipoArticuloEncomienda::PAQUETE) {
            $peso = filter_var($datos["peso"] ?? null, FILTER_VALIDATE_FLOAT);
            if ($peso === false || $peso <= 0 || $peso > 2000) {
                throw new EncomiendaRechazada("Artículo {$n}: indique el peso del paquete en libras.");
            }
        } else {
            $especial = filter_var($datos["especial"] ?? null, FILTER_VALIDATE_INT);
            if ($especial === false || $especial < 1) {
                throw new EncomiendaRechazada("Artículo {$n}: elija el tipo de encomienda especial.");
            }
        }

        return new self(
            tipo: $tipo,
            cantidad: $cantidad,
            peso: $peso,
            especialId: $especial,
            descripcion: $descripcion !== "" ? mb_substr($descripcion, 0, 255) : null,
            alto: $tipo === TipoArticuloEncomienda::PAQUETE ? $medida($datos["alto"] ?? null, "alto") : null,
            ancho: $tipo === TipoArticuloEncomienda::PAQUETE ? $medida($datos["ancho"] ?? null, "ancho") : null,
            largo: $tipo === TipoArticuloEncomienda::PAQUETE ? $medida($datos["largo"] ?? null, "largo") : null,
        );
    }

    /**
     * @return list<self>
     *
     * @throws EncomiendaRechazada
     */
    public static function lista(mixed $datos): array
    {
        $articulos = [];
        foreach (is_array($datos) ? array_values($datos) : [] as $i => $a) {
            $articulos[] = self::desdeArray(is_array($a) ? $a : [], $i);
        }
        if ($articulos === []) {
            throw new EncomiendaRechazada("Agregue al menos un artículo a la encomienda.");
        }
        if (count($articulos) > 30) {
            throw new EncomiendaRechazada("Demasiados artículos en una sola encomienda.");
        }

        return $articulos;
    }
}
