<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\TipoArticuloEncomienda;
use App\Migration\Salida\EncomiendaLegada;
use PHPUnit\Framework\TestCase;

final class EncomiendaLegadaTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function evento(int $id, string $fecha, int $estado, ?int $estacion = 1, ?int $salida = null, ?int $cliente = null): array
    {
        return ["id" => $id, "fecha" => $fecha, "estado_id" => $estado, "estacion" => $estacion, "salida_id" => $salida, "cliente_id" => $cliente];
    }

    /** Como en el legado: desembarcar y entregar en el mismo segundo, la entrega con id menor. */
    private static function viajeCompleto(): array
    {
        return [
            self::evento(15, "2026-09-02 16:57:14", 4, 6),
            self::evento(14, "2026-09-02 16:57:14", 5, 6, null, 899652),
            self::evento(12, "2026-09-01 18:33:26", 2, 1, 8122001),
            self::evento(11, "2026-09-01 07:05:41", 1, 1),
            self::evento(13, "2026-09-01 21:22:52", 3, 1, 8122001),
        ];
    }

    public function testOrdenaPorFechaYEnEmpateSigueElCicloDeVida(): void
    {
        $orden = array_column(EncomiendaLegada::ordenar(self::viajeCompleto()), "estado_id");

        self::assertSame([1, 2, 3, 4, 5], $orden);
    }

    public function testElEstadoEsElDeLaUltimaBitacoraDelLegado(): void
    {
        $eventos = EncomiendaLegada::ordenar(self::viajeCompleto());

        self::assertSame(EstadoEncomienda::ENTREGADA, EncomiendaLegada::estado(["ultima_bitacora_id" => 14], $eventos));
        // Sin `ultima_bitacora_id` (o fuera de los eventos), el último.
        self::assertSame(EstadoEncomienda::ENTREGADA, EncomiendaLegada::estado([], $eventos));
        self::assertSame(EstadoEncomienda::TRANSITO, EncomiendaLegada::estado(["ultima_bitacora_id" => 13], $eventos));
        self::assertSame(EstadoEncomienda::RECIBIDA, EncomiendaLegada::estado([], []));
        self::assertSame(EstadoEncomienda::ANULADA, EncomiendaLegada::estadoDe(7), "la cancelada del legado es una anulación");
    }

    public function testModalidadPorTipoDeDocumentoYBoleto(): void
    {
        self::assertSame(ModalidadEncomienda::PAGADA, EncomiendaLegada::modalidad(["tipo_documento_id" => 1], false));
        self::assertSame(ModalidadEncomienda::POR_COBRAR, EncomiendaLegada::modalidad(["tipo_documento_id" => 2], false));
        self::assertSame(ModalidadEncomienda::INTERNA, EncomiendaLegada::modalidad(["tipo_documento_id" => 3], false));
        self::assertSame(ModalidadEncomienda::INTERNA, EncomiendaLegada::modalidad(["tipo_documento_id" => 4], false));
        // Con su boleto migrado y sin factura propia: va en la venta del boleto.
        self::assertSame(ModalidadEncomienda::CON_BOLETO, EncomiendaLegada::modalidad(["tipo_documento_id" => 1, "factura_generada_id" => null], true));
        self::assertSame(ModalidadEncomienda::CON_BOLETO, EncomiendaLegada::modalidad(["tipo_documento_id" => 1, "factura_generada_id" => 9, "boleto_fg" => 9], true));
        // Con factura propia sigue su documento.
        self::assertSame(ModalidadEncomienda::PAGADA, EncomiendaLegada::modalidad(["tipo_documento_id" => 1, "factura_generada_id" => 9, "boleto_fg" => 8], true));
    }

    public function testUbicacionEnBodega(): void
    {
        $eventos = EncomiendaLegada::ordenar(self::viajeCompleto());
        $hastaDesembarcar = array_slice($eventos, 0, 4);
        $old = ["estacion_creacion_id" => 3, "estacion_origen_id" => 1];

        self::assertSame(6, EncomiendaLegada::ubicacion(EstadoEncomienda::DESEMBARCADA, $old, $hastaDesembarcar));
        self::assertSame(1, EncomiendaLegada::ubicacion(EstadoEncomienda::RECIBIDA, $old, array_slice($eventos, 0, 1)));
        self::assertSame(3, EncomiendaLegada::ubicacion(EstadoEncomienda::RECIBIDA, $old, []), "sin bitácora: donde se registró");
        self::assertNull(EncomiendaLegada::ubicacion(EstadoEncomienda::TRANSITO, $old, $eventos));
        self::assertNull(EncomiendaLegada::ubicacion(EstadoEncomienda::ENTREGADA, $old, $eventos));
    }

    public function testSalidasEnOrdenConTransbordo(): void
    {
        $eventos = [
            self::evento(1, "2026-09-01 08:00:00", 2, 1, 100),
            self::evento(2, "2026-09-01 09:00:00", 3, 1, 100),
            self::evento(3, "2026-09-01 15:00:00", 4, 5, 100),
            self::evento(4, "2026-09-02 08:00:00", 2, 5, 200),
        ];

        self::assertSame([100, 200], EncomiendaLegada::salidas(["primera_salida_id" => 100], $eventos));
        self::assertSame(5, EncomiendaLegada::ultimo($eventos, 4)["estacion"]);
        self::assertNull(EncomiendaLegada::ultimo($eventos, 5));
    }

    public function testArticuloDePaqueteEspecialYEfectivo(): void
    {
        $paquete = EncomiendaLegada::articulo([
            "tipo_encomienda_id" => 3, "cantidad" => 3, "peso" => "12", "alto" => "40", "ancho" => "0", "profundidad" => "30",
            "descripcion" => "  Caja   de ropa ", "precioCalculadoMonedaBase" => "100.00", "precioCalculado" => "13.00",
        ]);
        self::assertSame(TipoArticuloEncomienda::PAQUETE, $paquete["tipo"]);
        self::assertSame(["Caja de ropa", 3, 12.0, 40, null, 30], [$paquete["descripcion"], $paquete["cantidad"], $paquete["peso"], $paquete["alto"], $paquete["ancho"], $paquete["largo"]]);
        self::assertSame([3333, 10000], [$paquete["tarifa"], $paquete["importe"]], "en quetzales (moneda base)");

        $especial = EncomiendaLegada::articulo(["tipo_encomienda_id" => 2, "tipo_encomienda_especial_id" => "7", "cantidad" => 2, "peso" => "5", "precioCalculado" => "50"]);
        self::assertSame(TipoArticuloEncomienda::ESPECIAL, $especial["tipo"]);
        self::assertSame([7, null, 2500, 5000], [$especial["especial"], $especial["peso"], $especial["tarifa"], $especial["importe"]]);

        $efectivo = EncomiendaLegada::articulo(["tipo_encomienda_id" => 1, "cantidad" => 0, "descripcion" => "Q500"]);
        self::assertSame(TipoArticuloEncomienda::PAQUETE, $efectivo["tipo"]);
        self::assertSame(["Efectivo Q500", 1, 0], [$efectivo["descripcion"], $efectivo["cantidad"], $efectivo["importe"]]);
    }
}
