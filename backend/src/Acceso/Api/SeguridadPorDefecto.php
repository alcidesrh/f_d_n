<?php

declare(strict_types=1);

namespace App\Acceso\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Operation as GraphQlOperation;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\Catalogo\DefinicionRecurso;

/**
 * Denegar por defecto en la API genérica (ADR-039): cada operación de
 * GraphQL y REST de `App\Entity` sin `security` propia recibe la de su
 * `#[Recurso]`: leer con `{recurso}.ver` (o solo sesión si es de lectura
 * libre), crear y modificar con `.editar` (también sobre lo que queda tras
 * aplicar los cambios: no se mueve un bus a una empresa ajena) y borrar con
 * `.eliminar`; lo de solo lectura no se escribe y lo oculto no se publica.
 * Los recursos propios de `App\ApiResource` exigen sesión: sus resolvers
 * comprueban el recurso que se pide.
 *
 * Decora la fábrica de metadata por dentro de su caché (prioridad 0, la
 * caché es -10): se calcula una vez por compilación.
 */
final class SeguridadPorDefecto implements ResourceMetadataCollectionFactoryInterface
{
    private const SESION = "is_granted('ROLE_USER')";
    private const NUNCA = 'false';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $interna,
        private readonly CatalogoRecursos $recursos,
    ) {}

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $coleccion = $this->interna->create($resourceClass);
        $propio = str_starts_with($resourceClass, 'App\\ApiResource\\');
        $definicion = str_starts_with($resourceClass, 'App\\Entity\\') ? $this->recursos->de($resourceClass) : null;
        if ($definicion === null && !$propio) {
            return $coleccion;
        }

        foreach ($coleccion as $i => $recurso) {
            /** @var ApiResource $recurso */
            $operaciones = [];
            foreach ($recurso->getOperations() ?? [] as $nombre => $operacion) {
                $operaciones[$nombre] = $this->proteger($operacion, $definicion);
            }
            $graphql = [];
            foreach ($recurso->getGraphQlOperations() ?? [] as $nombre => $operacion) {
                $graphql[$nombre] = $this->proteger($operacion, $definicion);
            }
            $recurso = $recurso->withGraphQlOperations($graphql);
            $coleccion[$i] = $recurso->getOperations() === null ? $recurso : $recurso->withOperations(new Operations($operaciones));
        }

        return $coleccion;
    }

    /**
     * @template T of Operation
     *
     * @param T $operacion
     *
     * @return T
     */
    public function proteger(Operation $operacion, ?DefinicionRecurso $d): Operation
    {
        if ($operacion->getSecurity() !== null) {
            return $operacion;
        }
        if ($d === null) {
            return $operacion->withSecurity(self::SESION);
        }
        if ($d->oculto) {
            return $operacion->withSecurity(self::NUNCA)->withSecurityMessage('Este recurso no se publica.');
        }

        return match (self::tipo($operacion)) {
            'leer' => $operacion
                ->withSecurity($d->ver === null ? self::SESION : self::exigir($d->ver, $operacion instanceof CollectionOperationInterface ? null : 'object'))
                ->withSecurityMessage(sprintf('No tiene permiso para ver %s.', mb_strtolower($d->nombre))),
            'crear' => $d->editar === null ? self::soloLectura($operacion, $d) : $operacion
                ->withSecurity(self::exigir($d->editar))
                ->withSecurityPostDenormalize(self::exigir($d->editar, 'object'))
                ->withSecurityMessage(sprintf('No tiene permiso para crear en %s.', mb_strtolower($d->nombre)))
                ->withSecurityPostDenormalizeMessage('Fuera de su alcance: no puede crearlo en esa empresa.'),
            'modificar' => $d->editar === null ? self::soloLectura($operacion, $d) : $operacion
                ->withSecurity(self::exigir($d->editar, 'object'))
                ->withSecurityPostDenormalize(self::exigir($d->editar, 'object'))
                ->withSecurityMessage(sprintf('No tiene permiso para modificar %s.', mb_strtolower($d->nombre)))
                ->withSecurityPostDenormalizeMessage('Fuera de su alcance: no puede dejarlo en esa empresa.'),
            'eliminar' => $d->eliminar === null || !$d->borrable ? self::soloLectura($operacion, $d) : $operacion
                ->withSecurity(self::exigir($d->eliminar, 'object'))
                ->withSecurityMessage(sprintf('No tiene permiso para eliminar en %s.', mb_strtolower($d->nombre))),
        };
    }

    /** `leer`, `crear`, `modificar` o `eliminar`. */
    private static function tipo(Operation $o): string
    {
        if ($o instanceof DeleteOperationInterface) {
            return 'eliminar';
        }
        if ($o instanceof GraphQlOperation) {
            return match (true) {
                !$o instanceof Mutation => 'leer',
                $o->getName() === 'create' => 'crear',
                default => 'modificar',
            };
        }
        if ($o instanceof HttpOperation) {
            return match ($o->getMethod()) {
                'POST' => 'crear',
                'PUT', 'PATCH' => 'modificar',
                'DELETE' => 'eliminar',
                default => 'leer',
            };
        }

        return 'leer';
    }

    private static function exigir(string $capacidad, ?string $sujeto = null): string
    {
        return $sujeto === null ? sprintf("is_granted('%s')", $capacidad) : sprintf("is_granted('%s', %s)", $capacidad, $sujeto);
    }

    private static function soloLectura(Operation $o, DefinicionRecurso $d): Operation
    {
        return $o->withSecurity(self::NUNCA)
            ->withSecurityMessage(sprintf('%s no se modifica por aquí: use su pantalla.', $d->nombre));
    }
}
