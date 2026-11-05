<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use App\Acceso\Legado\ConsolidacionRoles;
use App\Acceso\Legado\EquivalenciasLegado;
use PHPUnit\Framework\TestCase;

/**
 * De los roles del legado a los puestos (ADR-052): nadie pierde nada de lo que
 * tenía (salvo lo que el legado le negaba), solo se amplía con lectura y se
 * usan los menos puestos posibles.
 */
final class ConsolidacionRolesTest extends TestCase
{
    /** El perfil de 60 usuarios: ocho roles apilados en cada estación. */
    private const TAQUILLA = [
        'ROLE_ADMIN_REIMPRIMIR_FACTURA', 'ROLE_CHECK_BOLETO_MOVIL', 'ROLE_CHECK_BOLETO_WEB', 'ROLE_REASIGNACION',
        'ROLE_RECEPTOR_ENCOMIENDAS', 'ROLE_SUPERVISOR_BOLETO', 'ROLE_SUPERVISOR_ENCOMIENDA', 'ROLE_VENDEDOR_BOLETOS',
    ];

    public function testLaTaquillaApiladaEsUnEncargadoMasLaCortesia(): void
    {
        $r = ConsolidacionRoles::consolidar(self::TAQUILLA, null, true);

        self::assertSame([ConsolidacionRoles::EMISOR_CORTESIA, ConsolidacionRoles::ENCARGADO_TAQUILLA], $r['puestos']);
        self::assertSame([], $r['ampliadas']);
        self::assertSame([], $r['sinCubrir']);
    }

    public function testNoVenderVoucherEsNoOtorgarLaCortesia(): void
    {
        $todos = [...self::TAQUILLA, 'ROLE_NO_VENDER_VOUCHER', 'ROLE_INSPECTOR_BOLETO'];
        $r = ConsolidacionRoles::consolidar(self::TAQUILLA, $todos, true);

        self::assertSame([ConsolidacionRoles::ENCARGADO_TAQUILLA], $r['puestos']);
        self::assertNotContains('venta.cortesia', ConsolidacionRoles::capacidades(ConsolidacionRoles::ENCARGADO_TAQUILLA));
    }

    public function testElChequeoWebYMovilSonUnSoloPuesto(): void
    {
        $r = ConsolidacionRoles::consolidar(['ROLE_CHECK_BOLETO_MOVIL', 'ROLE_CHECK_BOLETO_WEB'], null, true);

        self::assertSame([ConsolidacionRoles::CHEQUEO_BOLETO], $r['puestos']);
    }

    public function testLaLecturaDeOficinaSeUneEnElRevisorYSoloAmpliaLectura(): void
    {
        $r = ConsolidacionRoles::consolidar(['ROLE_INSPECTOR_BOLETO', 'ROLE_MANIFIESTO_ESPECIAL'], null, false);

        self::assertSame(['ROLE_REVISOR'], $r['puestos']);
        self::assertSame(['encomienda.ver'], $r['ampliadas']);
    }

    public function testConUnRolTotalBastaEse(): void
    {
        self::assertSame(['ROLE_ADMIN'], ConsolidacionRoles::consolidar(['ROLE_ADMIN', ...self::TAQUILLA])['puestos']);
        self::assertSame(['ROLE_SUPER_ADMIN'], ConsolidacionRoles::consolidar(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])['puestos']);
        self::assertSame([], ConsolidacionRoles::consolidar(self::TAQUILLA, ['ROLE_ADMIN', ...self::TAQUILLA], true)['puestos']);
    }

    public function testLoQueYaDaLaOficinaNoSeRepiteEnLaEstacion(): void
    {
        // Vende en la estación y ya ve boletos y salidas en todas: basta el vendedor.
        $r = ConsolidacionRoles::consolidar(['ROLE_ADMIN_REIMPRIMIR_FACTURA', 'ROLE_VENDEDOR_BOLETOS'], null, true, ['boleto.ver', 'salida.ver', 'encomienda.ver']);

        self::assertSame(['ROLE_VENDEDOR_BOLETOS'], $r['puestos']);
    }

    /** Cada rol del legado solo, y de a pares: nada se pierde y lo ampliado es lectura. */
    public function testNingunRolDelLegadoPierdeNiGanaEscritura(): void
    {
        $roles = array_keys(EquivalenciasLegado::ROLES);
        $combinaciones = array_map(static fn(string $r) => [$r], $roles);
        foreach ($roles as $i => $a) {
            foreach (array_slice($roles, $i + 1) as $b) {
                $combinaciones[] = [$a, $b];
            }
        }
        foreach ($combinaciones as $combinacion) {
            foreach ([true, false, null] as $porEstacion) {
                $antes = ConsolidacionRoles::capacidadesLegado($combinacion, $combinacion, $porEstacion);
                if (!in_array('venta.vender', $antes, true)) {
                    $antes = array_diff($antes, ['venta.cortesia']); // sin vender, la cortesía no se ejerce
                }
                $r = ConsolidacionRoles::consolidar($combinacion, null, $porEstacion);
                $despues = array_merge([], ...array_map(static fn(string $p) => ConsolidacionRoles::capacidades($p) ?? [], $r['puestos']));
                $caso = implode('+', $combinacion) . ' / ' . var_export($porEstacion, true);

                self::assertSame([], $r['sinCubrir'], $caso);
                self::assertSame([], array_values(array_diff($antes, $despues)), "$caso pierde");
                foreach (array_diff($despues, $antes) as $c) {
                    self::assertTrue(ConsolidacionRoles::esLectura($c), "$caso gana $c");
                }
                foreach ($r['puestos'] as $p) {
                    self::assertTrue($porEstacion === null || ConsolidacionRoles::porEstacion($p) === $porEstacion || in_array($p, ConsolidacionRoles::EN_AMBOS, true), "$caso: $p es de otro alcance");
                }
            }
        }
    }

    public function testCadaRetiradoTieneUnPuestoDeReemplazo(): void
    {
        foreach (ConsolidacionRoles::RETIRADOS as $rol => $reemplazo) {
            self::assertArrayNotHasKey($rol, ConsolidacionRoles::PUESTOS, $rol);
            self::assertTrue($reemplazo === null || isset(ConsolidacionRoles::PUESTOS[$reemplazo]), $rol);
        }
        foreach (array_keys(EquivalenciasLegado::ROLES) as $rol) {
            self::assertTrue(isset(ConsolidacionRoles::PUESTOS[$rol]) || array_key_exists($rol, ConsolidacionRoles::RETIRADOS), "$rol no es puesto ni está retirado");
        }
    }

    public function testSoloLaEmisionDeCortesiasDaCortesia(): void
    {
        foreach (ConsolidacionRoles::PUESTOS as $nombre => [, $caps]) {
            if ($nombre !== ConsolidacionRoles::EMISOR_CORTESIA) {
                self::assertNotContains('venta.cortesia', $caps, $nombre);
            }
        }
    }
}
