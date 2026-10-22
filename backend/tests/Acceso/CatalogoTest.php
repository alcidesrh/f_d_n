<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use ApiPlatform\Metadata\ApiResource;
use App\Acceso\Capacidad;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\Legado\EquivalenciasLegado;
use App\Acceso\Limite;
use App\Acceso\Recurso;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo es el contrato entre el código y los roles (ADR-039): lo que el
 * código exige tiene que existir en él (si no, el voter se abstiene y se
 * deniega sin aviso) y cada entidad publicada declara cómo se protege.
 */
final class CatalogoTest extends TestCase
{
    /** @return list<class-string> */
    private static function entidadesPublicadas(): array
    {
        $clases = [];
        foreach (glob(dirname(__DIR__, 2) . '/src/Entity/*.php') as $archivo) {
            $clase = 'App\\Entity\\' . basename($archivo, '.php');
            if (class_exists($clase) && (new \ReflectionClass($clase))->getAttributes(ApiResource::class, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                $clases[] = $clase;
            }
        }

        return $clases;
    }

    private static function catalogo(): Catalogo
    {
        return new Catalogo(CatalogoRecursos::desdeClases(self::entidadesPublicadas()));
    }

    public function testCadaEntidadPublicadaDeclaraComoSeProtege(): void
    {
        $sin = array_values(array_filter(
            self::entidadesPublicadas(),
            static fn(string $c) => (new \ReflectionClass($c))->getAttributes(Recurso::class) === [],
        ));

        self::assertSame([], $sin, 'Agregue #[Recurso(...)] a estas entidades.');
    }

    public function testLoQueExigeElCodigoExisteEnElCatalogo(): void
    {
        $catalogo = self::catalogo();
        $usados = [];
        $fuentes = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));
        foreach ($fuentes as $archivo) {
            if ($archivo->getExtension() !== 'php' || str_contains($archivo->getPathname(), '/EntitySistemaFdn/') || str_contains($archivo->getPathname(), '/Legado/')) {
                continue;
            }
            $codigo = (string) file_get_contents($archivo->getPathname());
            preg_match_all('/(?:isGranted|denyAccessUnlessGranted|IsGranted|is_granted)\(\s*["\']([a-z_]+\.[a-z_]+)["\']/', $codigo, $m1);
            preg_match_all('/public const [A-Z_]+ = ["\']([a-z_]+\.[a-z_]+)["\'];/', $codigo, $m2);
            foreach ([...$m1[1], ...$m2[1]] as $c) {
                $usados[$c][] = basename($archivo->getPathname());
            }
        }
        $faltan = array_filter($usados, static fn(string $c) => !$catalogo->existe($c), ARRAY_FILTER_USE_KEY);

        self::assertNotEmpty($usados);
        self::assertSame([], $faltan, 'Capacidades exigidas que no están en el catálogo.');
    }

    public function testLasEquivalenciasDelLegadoSonDelCatalogo(): void
    {
        $catalogo = self::catalogo();
        foreach (EquivalenciasLegado::ROLES as $rol => [, $capacidades]) {
            foreach ($capacidades as $c) {
                self::assertTrue($catalogo->existe($c), "$rol: «$c» no existe");
            }
        }
        $admin = EquivalenciasLegado::capacidades(EquivalenciasLegado::ADMIN, $catalogo->codigos());
        self::assertContains('config.editar', $admin);
        self::assertNotContains('migracion.ejecutar', $admin);
        self::assertNull(EquivalenciasLegado::capacidades(EquivalenciasLegado::SISTEMA, $catalogo->codigos()));
    }

    public function testCadaLimiteEsDeUnaCapacidadDelDominio(): void
    {
        foreach (Limite::cases() as $l) {
            self::assertContains($l, $l->capacidad()->limites());
        }
    }

    public function testElCrudGenericoComparteLosCodigosDelDominio(): void
    {
        $catalogo = self::catalogo();
        $recursos = CatalogoRecursos::desdeClases(self::entidadesPublicadas());

        self::assertSame(Capacidad::BOLETO_VER->value, $recursos->de('BoletoAsiento')?->ver);
        self::assertSame(Capacidad::SALIDA_VER->value, $recursos->de('Salida')?->ver);
        self::assertNull($recursos->de('Salida')?->editar, 'Las salidas se escriben por su gestión, no por el CRUD.');
        self::assertTrue($recursos->de('ApiToken')?->oculto);
        self::assertTrue($recursos->de('Enclave')?->lecturaLibre);
        self::assertFalse($recursos->de('Usuario')?->borrable);
        // El nombre de una capacidad del dominio no lo pisa el del CRUD.
        self::assertSame(Capacidad::RUTA_EDITAR->nombre(), $catalogo->definiciones()['ruta.editar']->nombre);
        self::assertSame(['empresa'], $catalogo->definiciones()['bus.editar']->dimensiones);
    }
}
