<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Acceso\Ambito;
use App\Acceso\Api\AlcanceEnColecciones;
use App\Acceso\Autorizacion;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Entity\BoletoAsiento;
use App\Entity\Salida;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Los listados genéricos filtran por la empresa del registro (ADR-039). Si la
 * empresa viene por una relación (`salida.empresa`) y el filtro de tenant
 * oculta esa salida, el LEFT JOIN queda vacío: el registro no puede pasar
 * por la rama "compartido" (sin empresa).
 */
final class AlcanceEnColeccionesTest extends TestCase
{
    private const CATALOGO = ['boleto.ver', 'salida.ver'];

    private function listado(string $clase, PermisosUsuario $permisos): string
    {
        $recursos = CatalogoRecursos::desdeClases([BoletoAsiento::class, Salida::class]);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn((new Usuario())->setId(7));
        $autorizacion = new Autorizacion($this->createStub(EntityManagerInterface::class), new Catalogo($recursos), $security);
        // Las permisos del usuario quedan en la caché de la petición: no hace falta base de datos.
        (new \ReflectionProperty(Autorizacion::class, 'cache'))->setValue($autorizacion, [7 => $permisos]);

        $nombres = new class implements QueryNameGeneratorInterface {
            public function generateJoinAlias(string $association): string
            {
                return $association . '_j';
            }

            public function generateParameterName(string $name): string
            {
                return $name;
            }
        };

        $qb = new QueryBuilder($this->createStub(EntityManagerInterface::class));
        $qb->select('b')->from($clase, 'b');
        (new AlcanceEnColecciones($recursos, $autorizacion))->applyToCollection($qb, $nombres, $clase);

        return $qb->getDQL();
    }

    private static function permisos(Ambito $ambito): PermisosUsuario
    {
        return new PermisosUsuario([new Concesion(1, 10, 'Boletero', false, self::CATALOGO, [], $ambito)], self::CATALOGO);
    }

    public function testEmpresaPorRelacionExigeLaFilaRelacionada(): void
    {
        $dql = $this->listado(BoletoAsiento::class, self::permisos(new Ambito([1], null)));

        $this->assertStringContainsString('LEFT JOIN b.salida salida_j', $dql);
        // Sin esta condición, una salida oculta por el filtro de tenant deja pasar su boleto por `IS NULL`.
        $this->assertStringContainsString('salida_j.id IS NOT NULL', $dql);
        $this->assertStringContainsString('salida_j.empresa IN (:alcance_e0)', $dql);
    }

    public function testEmpresaPropiaConservaLaRamaCompartida(): void
    {
        $dql = $this->listado(Salida::class, self::permisos(new Ambito([1], null)));

        $this->assertStringNotContainsString('IS NOT NULL', $dql);
        $this->assertStringContainsString('(b.empresa IN (:alcance_e0) OR b.empresa IS NULL)', $dql);
    }
}
