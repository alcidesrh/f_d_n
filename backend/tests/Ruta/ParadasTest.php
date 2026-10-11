<?php

declare(strict_types=1);

namespace App\Tests\Ruta;

use App\Ruta\Paradas;
use App\Ruta\RutaRechazada;
use PHPUnit\Framework\TestCase;

final class ParadasTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function ida(): array
    {
        return [
            ["enclaveId" => 1, "minutos" => 99],
            ["enclaveId" => 2, "minutos" => 60, "km" => 50, "permiteSubir" => true, "permiteBajar" => false, "tiempos" => [["claseId" => 7, "minutos" => 80]]],
            ["enclaveId" => 3, "minutos" => null],
            ["enclaveId" => 4, "minutos" => 240, "km" => 200, "tiempos" => [["claseId" => 7, "minutos" => 300]]],
        ];
    }

    public function testNormalizaLasParadas(): void
    {
        $p = Paradas::desdeArray(self::ida());

        $this->assertNull($p[0]["minutos"], "el origen no tiene minutos");
        $this->assertSame([7 => 80], $p[1]["tiempos"]);
        $this->assertFalse($p[1]["permiteBajar"]);
        $this->assertTrue($p[3]["permiteSubir"], "por defecto se sube y se baja");
    }

    public function testLaRutaDeRegresoCuentaDesdeElNuevoOrigen(): void
    {
        $vuelta = Paradas::invertir(Paradas::desdeArray(self::ida()));

        $this->assertSame([4, 3, 2, 1], array_column($vuelta, "enclaveId"));
        $this->assertSame([null, null, 180, 240], array_column($vuelta, "minutos"));
        $this->assertSame([null, null, 150, 200], array_column($vuelta, "km"));
        $this->assertSame([7 => 220], $vuelta[2]["tiempos"], "300 - 80 por la clase 7");
        $this->assertSame([7 => 300], $vuelta[3]["tiempos"]);
        $this->assertFalse($vuelta[2]["permiteSubir"], "donde no se bajaba a la ida, no se sube a la vuelta");
    }

    public function testAvisaSinRechazarLosMinutosQueRetroceden(): void
    {
        $p = Paradas::desdeArray([["enclaveId" => 1], ["enclaveId" => 2, "minutos" => 350], ["enclaveId" => 3, "minutos" => 300], ["enclaveId" => 4, "minutos" => 600]]);

        $this->assertSame([2], array_keys(Paradas::avisos($p)));
    }

    /** @return iterable<string, array{0: list<array<string, mixed>>, 1: string}> */
    public static function invalidas(): iterable
    {
        yield "una sola" => [[["enclaveId" => 1]], "paradas_insuficientes"];
        yield "repetida" => [[["enclaveId" => 1], ["enclaveId" => 2], ["enclaveId" => 1]], "parada_repetida"];
        yield "sin lugar" => [[["enclaveId" => 1], ["enclaveId" => 0]], "parada_sin_lugar"];
        yield "negativo" => [[["enclaveId" => 1], ["enclaveId" => 2, "minutos" => -5]], "valor_invalido"];
    }

    /** @param list<array<string, mixed>> $datos */
    #[\PHPUnit\Framework\Attributes\DataProvider("invalidas")]
    public function testRechaza(array $datos, string $codigo): void
    {
        try {
            Paradas::desdeArray($datos);
            $this->fail("Debía rechazar");
        } catch (RutaRechazada $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }
}
