<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use App\Acceso\Api\SeguridadPorDefecto;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Entity\ApiToken;
use App\Entity\Bus;
use App\Entity\Enclave;
use App\Entity\Salida;
use PHPUnit\Framework\TestCase;

final class SeguridadPorDefectoTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> nombre de operación → [security, securityPostDenormalize] */
    private static function seguridad(string $clase): array
    {
        $interna = new class ($clase) implements ResourceMetadataCollectionFactoryInterface {
            public function __construct(private readonly string $clase) {}

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($this->clase, [(new ApiResource(class: $this->clase))
                    ->withOperations(new Operations([
                        'get' => new Get(name: 'get'),
                        'lista' => new GetCollection(name: 'lista'),
                        'post' => new Post(name: 'post'),
                        'patch' => new Patch(name: 'patch'),
                        'delete' => new Delete(name: 'delete'),
                        'propia' => new Get(name: 'propia', security: "is_granted('ROLE_USER')"),
                    ]))
                    ->withGraphQlOperations([
                        'item_query' => new Query(name: 'item_query'),
                        'collection_query' => new QueryCollection(name: 'collection_query'),
                        'create' => new Mutation(name: 'create'),
                        'update' => new Mutation(name: 'update'),
                        'delete' => new DeleteMutation(name: 'delete'),
                    ])]);
            }
        };
        $coleccion = (new SeguridadPorDefecto($interna, CatalogoRecursos::desdeClases([$clase])))->create($clase);
        $resultado = [];
        foreach ($coleccion[0]->getOperations() as $nombre => $o) {
            $resultado["rest.$nombre"] = [$o->getSecurity(), $o->getSecurityPostDenormalize()];
        }
        foreach ($coleccion[0]->getGraphQlOperations() as $nombre => $o) {
            $resultado["gql.$nombre"] = [$o->getSecurity(), $o->getSecurityPostDenormalize()];
        }

        return $resultado;
    }

    public function testCadaOperacionExigeSuCapacidad(): void
    {
        $s = self::seguridad(Bus::class);

        self::assertSame(["is_granted('bus.ver', object)", null], $s['rest.get']);
        self::assertSame(["is_granted('bus.ver')", null], $s['rest.lista']);
        self::assertSame(["is_granted('bus.editar')", "is_granted('bus.editar', object)"], $s['rest.post']);
        self::assertSame(["is_granted('bus.editar', object)", "is_granted('bus.editar', object)"], $s['rest.patch']);
        self::assertSame(["is_granted('bus.eliminar', object)", null], $s['rest.delete']);
        self::assertSame(["is_granted('bus.ver', object)", null], $s['gql.item_query']);
        self::assertSame(["is_granted('bus.ver')", null], $s['gql.collection_query']);
        self::assertSame(["is_granted('bus.editar')", "is_granted('bus.editar', object)"], $s['gql.create']);
        self::assertSame(["is_granted('bus.editar', object)", "is_granted('bus.editar', object)"], $s['gql.update']);
        self::assertSame(["is_granted('bus.eliminar', object)", null], $s['gql.delete']);
    }

    public function testRespetaLaSeguridadPropia(): void
    {
        self::assertSame(["is_granted('ROLE_USER')", null], self::seguridad(Bus::class)['rest.propia']);
    }

    public function testLecturaLibreSoloPideSesion(): void
    {
        $s = self::seguridad(Enclave::class);

        self::assertSame("is_granted('ROLE_USER')", $s['gql.collection_query'][0]);
        self::assertSame("is_granted('enclave.editar', object)", $s['gql.update'][0]);
    }

    public function testSoloLecturaNoSeEscribe(): void
    {
        $s = self::seguridad(Salida::class);

        self::assertSame("is_granted('salida.ver', object)", $s['gql.item_query'][0]);
        self::assertSame('false', $s['gql.update'][0]);
        self::assertSame('false', $s['rest.post'][0]);
        self::assertSame('false', $s['gql.delete'][0]);
    }

    public function testLoOcultoNoSePublica(): void
    {
        foreach (self::seguridad(ApiToken::class) as $nombre => [$security]) {
            if ($nombre !== 'rest.propia') {
                self::assertSame('false', $security, $nombre);
            }
        }
    }
}
