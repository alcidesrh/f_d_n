<?php

declare(strict_types=1);

namespace App\Tests\Autorizacion;

use App\Acceso\Ambito;
use App\Acceso\Capacidad;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use App\Autorizacion\Autorizable;
use App\Autorizacion\AutorizacionRechazada;
use App\Autorizacion\ReglasAutorizacion;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/** Autorizaciones, pases de un solo uso (ADR-040): ciclo, qué cubren y quién las resuelve. */
final class AutorizacionTest extends TestCase
{
    private const CATALOGO = ['boleto.anular', 'boleto.reasignar', 'venta.cortesia', 'autorizacion.aprobar'];

    private static function usuario(int $id): Usuario
    {
        return (new Usuario())->setId($id);
    }

    private static function solicitud(Autorizable $a, ?Ubicacion $donde = null, int $solicitante = 1, string $creada = '2026-10-22 08:00'): SolicitudAutorizacion
    {
        return new SolicitudAutorizacion(
            self::usuario($solicitante),
            $a->capacidad->value,
            $a->dispensa,
            $a->sujeto,
            $a->ids,
            $a->cantidad,
            $donde ?? Ubicacion::empresa(1)->conEstacion(10)->conAgencia(null),
            null,
            null,
            [],
            'El pasajero perdió el bus',
            new \DateTimeImmutable($creada),
        );
    }

    /** @param list<string> $capacidades */
    private static function permisos(array $capacidades, Ambito $ambito): PermisosUsuario
    {
        return new PermisosUsuario([new Concesion(1, 10, 'Rol', false, $capacidades, [], $ambito)], self::CATALOGO);
    }

    private function codigo(callable $f): ?string
    {
        try {
            $f();
        } catch (AutorizacionRechazada $e) {
            return $e->codigo;
        }

        return null;
    }

    public function testCadaCapacidadDeclaraDeQueDispensa(): void
    {
        $this->assertNull($this->codigo(fn() => ReglasAutorizacion::exigirAutorizable(Capacidad::BOLETO_REASIGNAR, Dispensa::HORA)));
        $this->assertNull($this->codigo(fn() => ReglasAutorizacion::exigirAutorizable(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE)));
        // Una cortesía no tiene hora que dispensar, y programar salidas no se autoriza por excepción.
        $this->assertSame('no_autorizable', $this->codigo(fn() => ReglasAutorizacion::exigirAutorizable(Capacidad::VENTA_CORTESIA, Dispensa::HORA)));
        $this->assertSame('no_autorizable', $this->codigo(fn() => ReglasAutorizacion::exigirAutorizable(Capacidad::SALIDA_CREAR, Dispensa::PERMISO)));
        // Cada límite que se puede pasar con una autorización es de una capacidad que lo admite.
        foreach (Capacidad::cases() as $c) {
            if (in_array(Dispensa::LIMITE, $c->dispensas(), true)) {
                $this->assertNotSame([], $c->limites(), $c->value);
            }
        }
    }

    public function testLaSolicitudSeAbreSeApruebaYSeUsaUnaVez(): void
    {
        $s = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [5, 3]));
        $this->assertSame([3, 5], $s->getSujetoIds());
        $this->assertSame(2, $s->getCantidad());
        $this->assertSame(EstadoAutorizacion::PENDIENTE, $s->estadoEn(new \DateTimeImmutable('2026-10-22 09:00')));

        $s->aprobar(self::usuario(2), '  Autorizado por gerencia ', new \DateTimeImmutable('2026-10-22 20:00'));
        $this->assertSame('Autorizado por gerencia', $s->getRespuesta());
        // La vigencia corre desde la aprobación.
        $this->assertSame('2026-10-23 20:00', $s->getVenceEn()->format('Y-m-d H:i'));
        $this->assertSame(EstadoAutorizacion::APROBADA, $s->estadoEn(new \DateTimeImmutable('2026-10-23 10:00')));

        $s->usar(new \DateTimeImmutable('2026-10-23 10:00'));
        $this->assertSame(EstadoAutorizacion::USADA, $s->getEstado());
        $this->assertSame('autorizacion_cerrada', $this->codigo(fn() => $s->usar(new \DateTimeImmutable('2026-10-23 10:01'))));
        $this->assertSame('autorizacion_cerrada', $this->codigo(fn() => $s->retirar(new \DateTimeImmutable('2026-10-23 10:01'))));
    }

    public function testVenceSinResolverYNoSeApruebaVencida(): void
    {
        $s = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [3]));
        $vencida = new \DateTimeImmutable('2026-10-23 08:00');
        $this->assertSame(EstadoAutorizacion::VENCIDA, $s->estadoEn($vencida));
        $this->assertSame('autorizacion_cerrada', $this->codigo(fn() => $s->aprobar(self::usuario(2), null, $vencida)));
    }

    public function testPedirYRechazarExigenMotivo(): void
    {
        $this->assertSame('falta_motivo', $this->codigo(fn() => new SolicitudAutorizacion(
            self::usuario(1), 'boleto.anular', Dispensa::HORA, SolicitudAutorizacion::SUJETO_BOLETOS, [3], 1, Ubicacion::ninguna(), null, null, [], '  ', new \DateTimeImmutable(),
        )));
        $s = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [3]));
        $this->assertSame('falta_motivo', $this->codigo(fn() => $s->rechazar(self::usuario(2), ' ', new \DateTimeImmutable('2026-10-22 09:00'))));
        $s->rechazar(self::usuario(2), 'No corresponde', new \DateTimeImmutable('2026-10-22 09:00'));
        $this->assertSame(EstadoAutorizacion::RECHAZADA, $s->estadoEn(new \DateTimeImmutable('2026-10-25')));
    }

    public function testUnPaseCubreLoPedidoYNadaMas(): void
    {
        $s = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_REASIGNAR, Dispensa::HORA, [3, 4]));
        $ahora = new \DateTimeImmutable('2026-10-22 09:00');
        $a = Autorizable::boletos(Capacidad::BOLETO_REASIGNAR, Dispensa::HORA, [3]);
        $this->assertFalse(ReglasAutorizacion::cubre($s, 1, $a, $ahora), 'pendiente todavía no cubre');

        $s->aprobar(self::usuario(2), null, $ahora);
        $this->assertTrue(ReglasAutorizacion::cubre($s, 1, $a, $ahora));
        $this->assertTrue(ReglasAutorizacion::cubre($s, 1, Autorizable::boletos(Capacidad::BOLETO_REASIGNAR, Dispensa::HORA, [4, 3]), $ahora));
        $this->assertFalse(ReglasAutorizacion::cubre($s, 1, Autorizable::boletos(Capacidad::BOLETO_REASIGNAR, Dispensa::HORA, [3, 9]), $ahora), 'un boleto que no pidió');
        $this->assertFalse(ReglasAutorizacion::cubre($s, 7, $a, $ahora), 'es de quien la pidió');
        $this->assertFalse(ReglasAutorizacion::cubre($s, 1, Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [3]), $ahora), 'otra operación');
        $this->assertFalse(ReglasAutorizacion::cubre($s, 1, Autorizable::boletos(Capacidad::BOLETO_REASIGNAR, Dispensa::LIMITE, [3]), $ahora), 'otra regla');

        $cortesia = self::solicitud(Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE, 50, 2));
        $cortesia->aprobar(self::usuario(2), null, $ahora);
        $this->assertTrue(ReglasAutorizacion::cubre($cortesia, 1, Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE, 50, 2), $ahora));
        $this->assertFalse(ReglasAutorizacion::cubre($cortesia, 1, Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE, 50, 3), $ahora), 'más asientos');
        $this->assertFalse(ReglasAutorizacion::cubre($cortesia, 1, Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE, 51, 1), $ahora), 'otra salida');
    }

    public function testQuienResuelve(): void
    {
        $donde = Ubicacion::empresa(1)->conEstacion(10)->conAgencia(null);
        $hora = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [3]), $donde);
        $permiso = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::PERMISO, [3]), $donde);

        $aprueba = self::permisos(['autorizacion.aprobar'], new Ambito([1], [10]));
        $this->assertNull(ReglasAutorizacion::rechazoParaResolver($aprueba, 2, $hora), 'fuera de hora basta con aprobar');
        $this->assertNotNull(ReglasAutorizacion::rechazoParaResolver($aprueba, 2, $permiso), 'sin permiso: tiene que poder hacerlo');
        $this->assertNull(ReglasAutorizacion::rechazoParaResolver(self::permisos(['autorizacion.aprobar', 'boleto.anular'], new Ambito([1], [10])), 2, $permiso));

        $this->assertNotNull(ReglasAutorizacion::rechazoParaResolver($aprueba, 1, $hora), 'no resuelve la suya');
        $this->assertNotNull(ReglasAutorizacion::rechazoParaResolver(self::permisos(['autorizacion.aprobar'], new Ambito([2], null)), 2, $hora), 'otra empresa');
        $this->assertNotNull(ReglasAutorizacion::rechazoParaResolver(self::permisos(['boleto.anular'], Ambito::total()), 2, $hora), 'sin autorizacion.aprobar');
    }

    public function testSeAvisaPrimeroALosMasCercanos(): void
    {
        $s = self::solicitud(Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, [3]), Ubicacion::empresa(1)->conEstacion(10)->conAgencia(null));

        $this->assertSame(0, ReglasAutorizacion::cercania(self::permisos(['autorizacion.aprobar'], new Ambito([1], [10])), $s), 'la estación');
        $this->assertSame(1, ReglasAutorizacion::cercania(self::permisos(['autorizacion.aprobar'], new Ambito([1], null)), $s), 'la empresa');
        $this->assertSame(2, ReglasAutorizacion::cercania(self::permisos(['autorizacion.aprobar'], Ambito::total()), $s), 'todas');
        $this->assertNull(ReglasAutorizacion::cercania(self::permisos(['autorizacion.aprobar'], new Ambito([2], null)), $s), 'no aprueba ahí');
    }

    public function testLoQueSePideViajaIdaYVuelta(): void
    {
        $a = Autorizable::desdeArray(['capacidad' => 'boleto.anular', 'dispensa' => 'hora', 'sujeto' => 'boletos', 'ids' => [4, '3', 0]]);
        $this->assertSame([3, 4], $a->ids);
        $this->assertSame(2, $a->cantidad);
        $this->assertSame('anular boletos', $a->toArray()['operacion']);
        $this->assertSame('dispensa_invalida', $this->codigo(fn() => Autorizable::desdeArray(['capacidad' => 'boleto.anular', 'dispensa' => 'magia', 'sujeto' => 'boletos', 'ids' => [1]])));
        $this->assertSame('sin_registros', $this->codigo(fn() => Autorizable::desdeArray(['capacidad' => 'boleto.anular', 'dispensa' => 'hora', 'sujeto' => 'boletos', 'ids' => []])));

        $donde = Ubicacion::empresa(null)->conEstacion(10)->conAgencia(null);
        $this->assertSame($donde->toArray(), Ubicacion::desdeArray($donde->toArray())->toArray());
    }
}
