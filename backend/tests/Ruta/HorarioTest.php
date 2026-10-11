<?php

declare(strict_types=1);

namespace App\Tests\Ruta;

use App\Entity\Enum\PrecisionHora;
use App\Migration\Salida\RutaLegada;
use App\Ruta\Horario;
use PHPUnit\Framework\TestCase;

final class HorarioTest extends TestCase
{
    /** @return list<array{orden: int, enclave: int, minutos: ?int, subir: bool, bajar: bool}> */
    private static function paradas(?int ...$minutos): array
    {
        return array_map(
            static fn(int $i, ?int $m) => ["orden" => $i, "enclave" => 100 + $i, "minutos" => $m, "subir" => true, "bajar" => $i > 0],
            array_keys($minutos),
            $minutos,
        );
    }

    public function testElOrigenEsExactoYLasDemasSeEstimanConSusMinutos(): void
    {
        $h = Horario::calcular(new \DateTimeImmutable("2026-10-10 22:00"), self::paradas(null, 90, 240));

        $this->assertSame(PrecisionHora::EXACTA, $h[0]["precision"]);
        $this->assertSame("2026-10-10 22:00", $h[0]["hora"]->format("Y-m-d H:i"));
        $this->assertSame(PrecisionHora::ESTIMADA, $h[1]["precision"]);
        $this->assertSame("2026-10-10 23:30", $h[1]["hora"]->format("Y-m-d H:i"));
        // Cruza la medianoche: la parada queda en el día siguiente.
        $this->assertSame("2026-10-11 02:00", $h[2]["hora"]->format("Y-m-d H:i"));
        $this->assertSame([100, 101, 102], array_column($h, "enclave"));
        $this->assertFalse($h[0]["bajar"]);
    }

    public function testSinMinutosLaHoraEsLaDelOrigenYSeMarcaDesconocida(): void
    {
        $h = Horario::calcular(new \DateTimeImmutable("2026-10-10 06:00"), self::paradas(null, null, 120));

        $this->assertSame(PrecisionHora::DESCONOCIDA, $h[1]["precision"]);
        $this->assertSame("06:00", $h[1]["hora"]->format("H:i"));
        $this->assertSame(PrecisionHora::ESTIMADA, $h[2]["precision"]);
    }

    public function testUnaParadaNuncaQuedaAntesQueLaAnterior(): void
    {
        $h = Horario::calcular(new \DateTimeImmutable("2026-10-10 06:00"), self::paradas(null, 120, 90, null, 30));

        $this->assertSame("08:00", $h[2]["hora"]->format("H:i"));
        // La desconocida no cuenta como anterior: la siguiente se compara con la última conocida.
        $this->assertSame("08:00", $h[4]["hora"]->format("H:i"));
    }

    public function testTramoDeUnaRutaDelLegado(): void
    {
        $ruta = new RutaLegada(7, [1 => 0, 5 => 1, 9 => 2, 3 => 3]);

        $this->assertSame([1, 3], $ruta->tramo(5, 3));
        $this->assertNull($ruta->tramo(9, 5), "hacia atrás no es un tramo");
        $this->assertNull($ruta->tramo(1, 42), "una estación fuera de la ruta");
        $this->assertSame(3, $ruta->ultima());
    }
}
