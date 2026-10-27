<?php

declare(strict_types=1);

namespace App\ConfiguracionListado;

use App\Acceso\PermisosUsuario;
use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\ListConfiguration;
use App\Entity\Role;
use App\Services\EntityConfigSynchronizer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Configuraciones del listado de cada entidad por rol (ADR-043): la que le
 * toca al usuario con sesión y la edición desde `/configuracion/entidades`.
 *
 * Toda escritura bloquea la `EntityConfiguration` de la entidad: las reglas
 * (`Reglas`: un rol en una sola, una sola por defecto) se comprueban sobre
 * todas sus configuraciones sin que otra petición cambie alguna entre medio.
 * Los navegadores se enteran por la revisión de la metadata (ADR-036).
 */
final class ConfiguracionesListado
{
    /** Lo de una columna que se edita (el campo y su tipo vienen del mapeo). */
    private const COLUMNA = ["label", "position", "visible", "attrs", "sortable", "filterable", "autocomplete", "editable", "width"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityConfigSynchronizer $sincronizador,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Lo que usan el listado y el formulario del usuario: los campos del
     * formulario y la configuración del listado que le toca. Null si la
     * entidad no se configura.
     *
     * @return array<string, mixed>|null
     */
    public function vigente(string $entidad, PermisosUsuario $permisos): ?array
    {
        $config = $this->configuracion($entidad);
        if ($config === null) {
            return null;
        }
        $listado = Reglas::para($config->getListConfigurations(), $permisos->roles());

        return [
            "entityClass" => $config->getEntityClass(),
            "formFields" => $this->normalizer->normalize($config->getFormFields()->getValues(), "json", ["groups" => ["read:dto"]]),
            "listConfig" => $listado === null ? null : ["id" => $listado->getId(), "nombre" => $listado->getNombre()],
            "listOptions" => $listado?->getListOptions(),
            "collectionFieldConfig" => $listado === null ? [] : $this->columnas($listado),
        ];
    }

    /**
     * Para el editor: todas las configuraciones del listado de la entidad, por
     * prioridad, y los roles que se pueden asociar.
     *
     * @return array{configuraciones: list<array<string, mixed>>, roles: list<array{id: int, nombre: string}>}
     */
    public function editor(string $entidad): array
    {
        $config = $this->configuracion($entidad) ?? throw new ConfiguracionRechazada("No existe la entidad {$entidad}.", "entidad_desconocida", 404);

        return $this->respuesta($config);
    }

    /**
     * `{ nombre, roles: [id], desde?: id }`: una configuración nueva, copia de
     * `desde` (o de la por defecto) en columnas y opciones.
     *
     * @return array{configuraciones: list<array<string, mixed>>, roles: list<array{id: int, nombre: string}>, creada: int}
     */
    public function crear(string $entidad, array $datos): array
    {
        $creada = $this->escribir($entidad, function (EntityConfiguration $config) use ($datos): ListConfiguration {
            $configuraciones = Reglas::ordenadas($config->getListConfigurations());
            $origen = isset($datos["desde"])
                ? $this->deLaEntidad($config, (int) $datos["desde"])
                : (array_values(array_filter($configuraciones, static fn(ListConfiguration $c) => $c->esPorDefecto()))[0] ?? $configuraciones[0] ?? null);
            $roles = $this->roles($datos["roles"] ?? []);
            $this->exigir(Reglas::rechazoRoles($configuraciones, null, $roles));
            $ultima = $configuraciones === [] ? 0 : end($configuraciones)->getPosition();
            $nueva = (new ListConfiguration($config, $this->nombre($datos), $ultima + 1))->setRoles($roles);
            if ($origen !== null) {
                $nueva->copiarDe($origen);
            }
            $this->em->persist($nueva);

            return $nueva;
        });

        return [...$this->editor($entidad), "creada" => (int) $creada->getId()];
    }

    /**
     * `{ nombre?, roles?: [id], listOptions?, columnas?: [{ id, label, position, visible, … }] }`:
     * lo ausente no cambia.
     *
     * @return array{configuraciones: list<array<string, mixed>>, roles: list<array{id: int, nombre: string}>}
     */
    public function guardar(string $entidad, int $id, array $datos): array
    {
        $this->escribir($entidad, function (EntityConfiguration $config) use ($id, $datos): void {
            $listado = $this->deLaEntidad($config, $id);
            if (\array_key_exists("nombre", $datos)) {
                $listado->setNombre($this->nombre($datos));
            }
            if (\array_key_exists("roles", $datos)) {
                $roles = $this->roles($datos["roles"] ?? []);
                $this->exigir(Reglas::rechazoRoles($config->getListConfigurations(), $listado, $roles));
                $listado->setRoles($roles);
            }
            if (\array_key_exists("listOptions", $datos)) {
                $listado->setListOptions(\is_array($datos["listOptions"]) ? $datos["listOptions"] : null);
            }
            $columnas = [];
            foreach ($listado->getCollectionFieldConfig() as $columna) {
                $columnas[(int) $columna->getId()] = $columna;
            }
            foreach ((array) ($datos["columnas"] ?? []) as $dato) {
                $columna = \is_array($dato) ? ($columnas[(int) ($dato["id"] ?? 0)] ?? null) : null;
                if ($columna === null) {
                    throw new ConfiguracionRechazada("Una de las columnas no es de esta configuración.");
                }
                $columna->loadData(array_intersect_key($dato, array_flip(self::COLUMNA)));
            }
            $config->markAsUpdated();
        });

        return $this->editor($entidad);
    }

    /** @return array{configuraciones: list<array<string, mixed>>, roles: list<array{id: int, nombre: string}>} */
    public function eliminar(string $entidad, int $id): array
    {
        $this->escribir($entidad, function (EntityConfiguration $config) use ($id): void {
            $listado = $this->deLaEntidad($config, $id);
            $this->exigir(Reglas::rechazoEliminar($listado));
            $config->removeListConfiguration($listado);
            $this->em->remove($listado);
        });

        return $this->editor($entidad);
    }

    /**
     * @template T
     *
     * @param callable(EntityConfiguration): T $operacion
     *
     * @return T
     */
    private function escribir(string $entidad, callable $operacion): mixed
    {
        return $this->em->wrapInTransaction(function () use ($entidad, $operacion) {
            $config = $this->configuracion($entidad) ?? throw new ConfiguracionRechazada("No existe la entidad {$entidad}.", "entidad_desconocida", 404);
            $this->em->lock($config, LockMode::PESSIMISTIC_WRITE);
            $resultado = $operacion($config);
            $this->em->flush();

            return $resultado;
        });
    }

    /** La configuración de la entidad; la crea si la entidad se configura y todavía no la tiene. */
    private function configuracion(string $entidad): ?EntityConfiguration
    {
        $config = $this->em->getRepository(EntityConfiguration::class)->findOneBy(["entityClass" => $entidad]);
        if ($config === null && \in_array($entidad, $this->sincronizador->entidades(), true)) {
            $config = $this->sincronizador->syncEntity($entidad);
        }

        return $config;
    }

    private function deLaEntidad(EntityConfiguration $config, int $id): ListConfiguration
    {
        foreach ($config->getListConfigurations() as $listado) {
            if ($listado->getId() === $id) {
                return $listado;
            }
        }
        throw new ConfiguracionRechazada("La configuración no existe o no es de {$config->getEntityClass()}.", "configuracion_desconocida", 404);
    }

    /** @return list<Role> */
    private function roles(mixed $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), \is_array($ids) ? $ids : [])));
        if ($ids === []) {
            return [];
        }
        $roles = $this->em->getRepository(Role::class)->findBy(["id" => $ids]);
        if (\count($roles) !== \count($ids)) {
            throw new ConfiguracionRechazada("Alguno de los roles no existe.");
        }

        return $roles;
    }

    private function nombre(array $datos): string
    {
        $nombre = trim((string) ($datos["nombre"] ?? ""));
        if ($nombre === "" || mb_strlen($nombre) > 120) {
            throw new ConfiguracionRechazada("El nombre es obligatorio (hasta 120 caracteres).");
        }

        return $nombre;
    }

    private function exigir(?string $rechazo): void
    {
        if ($rechazo !== null) {
            throw new ConfiguracionRechazada($rechazo);
        }
    }

    /** @return list<array<string, mixed>> */
    private function columnas(ListConfiguration $listado): array
    {
        $columnas = $listado->getCollectionFieldConfig()->getValues();
        usort($columnas, static fn(CollectionFieldConfig $a, CollectionFieldConfig $b) => $a->getPosition() <=> $b->getPosition());

        return array_map(
            fn(CollectionFieldConfig $c) => ["id" => $c->getId(), ...$this->normalizer->normalize($c, "json", ["groups" => ["read:dto"]])],
            $columnas,
        );
    }

    /** @return array{configuraciones: list<array<string, mixed>>, roles: list<array{id: int, nombre: string}>} */
    private function respuesta(EntityConfiguration $config): array
    {
        $roles = array_values(array_filter($this->em->getRepository(Role::class)->findBy([], ["nombre" => "ASC"]), static fn(Role $r) => !$r->esBase()));

        return [
            "configuraciones" => array_map(fn(ListConfiguration $l) => [
                "id" => $l->getId(),
                "nombre" => $l->getNombre(),
                "position" => $l->getPosition(),
                "porDefecto" => $l->esPorDefecto(),
                "roles" => $l->rolIds(),
                "listOptions" => $l->getListOptions(),
                "collectionFieldConfig" => $this->columnas($l),
            ], Reglas::ordenadas($config->getListConfigurations())),
            "roles" => array_map(static fn(Role $r) => ["id" => (int) $r->getId(), "nombre" => (string) $r->getNombre(), "descripcion" => $r->getDescripcion()], $roles),
        ];
    }
}
