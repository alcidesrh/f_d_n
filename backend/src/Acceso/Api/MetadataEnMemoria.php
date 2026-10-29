<?php

declare(strict_types=1);

namespace App\Acceso\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * La metadata de cada recurso, una vez por proceso. API Platform pone la
 * fábrica de Doctrine (prioridad -50) por fuera de su caché (-10): cada
 * `create()` vuelve a preguntarle a Doctrine si la clase es una entidad
 * (leyendo sus atributos), y armar el schema de GraphQL lo llama cientos de
 * veces por petición. La metadata no cambia sin un despliegue, que reinicia
 * los workers de FrankenPHP; por eso no se vacía entre peticiones.
 */
final class MetadataEnMemoria implements ResourceMetadataCollectionFactoryInterface
{
    /** @var array<string, ResourceMetadataCollection> */
    private array $porClase = [];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $interna,
    ) {}

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        return $this->porClase[$resourceClass] ??= $this->interna->create($resourceClass);
    }
}
