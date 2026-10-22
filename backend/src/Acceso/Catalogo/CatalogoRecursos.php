<?php

declare(strict_types=1);

namespace App\Acceso\Catalogo;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Acceso\Recurso;

/**
 * Las entidades de `App\Entity` publicadas en API Platform y cómo se
 * protegen (`#[Recurso]`). Se arma una vez por proceso: sale del código.
 */
final class CatalogoRecursos
{
    /** @var array<class-string, DefinicionRecurso>|null */
    private ?array $recursos = null;

    /** @var list<class-string>|null clases fijas (tests) */
    private ?array $clases = null;

    public function __construct(
        private readonly ?ResourceNameCollectionFactoryInterface $nombres = null,
    ) {}

    /** @param list<class-string> $clases */
    public static function desdeClases(array $clases): self
    {
        $catalogo = new self();
        $catalogo->clases = $clases;

        return $catalogo;
    }

    /** @return array<class-string, DefinicionRecurso> */
    public function recursos(): array
    {
        if ($this->recursos !== null) {
            return $this->recursos;
        }
        $clases = $this->clases ?? [...($this->nombres?->create() ?? [])];
        $recursos = [];
        foreach ($clases as $clase) {
            if (!str_starts_with($clase, 'App\\Entity\\') || !class_exists($clase)) {
                continue;
            }
            $atributo = (new \ReflectionClass($clase))->getAttributes(Recurso::class)[0] ?? null;
            $recursos[$clase] = DefinicionRecurso::de($clase, $atributo?->newInstance());
        }
        ksort($recursos);

        return $this->recursos = $recursos;
    }

    /** Por clase (FQCN, o el objeto) o por nombre corto (`BoletoTarifa`). */
    public function de(string|object $recurso): ?DefinicionRecurso
    {
        $clase = is_object($recurso) ? $recurso::class : $recurso;
        $recursos = $this->recursos();
        if (isset($recursos[$clase])) {
            return $recursos[$clase];
        }
        // Proxies de Doctrine y nombres cortos.
        foreach ($recursos as $c => $definicion) {
            if (is_object($recurso) ? $recurso instanceof $c : $definicion->nombreCorto === $clase) {
                return $definicion;
            }
        }

        return null;
    }
}
