<?php

declare(strict_types=1);

namespace App\Metadata;

use ApiPlatform\GraphQl\Type\SchemaBuilderInterface;
use App\Entity\EntityConfiguration;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use GraphQL\Utils\SchemaPrinter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Huellas de lo que el frontend guarda en el navegador, tal como está ahora:
 * el schema GraphQL y la configuración de cada entidad (ADR-036).
 *
 * La configuración se lee con DBAL, de lo confirmado en la base, y no de las
 * entidades en memoria (al terminar una petición pueden tener cambios sin
 * guardar o estar en otro orden). Entra cada columna de `entity_configuration`
 * (menos su id y `updatedAt`, que cambia en cada guardado) y de sus hijas
 * `ConfiguracionDelCliente` en su orden, a cualquier profundidad (campos del
 * formulario; configuraciones del listado con sus columnas, ADR-043), más los
 * ids de sus relaciones a muchos (los roles de cada configuración del
 * listado): una columna nueva entra sola, sin tocar este código.
 */
final class HuellasVigentes
{
    private const ESQUEMA = "esquema_graphql";

    public function __construct(
        #[Autowire(service: "api_platform.graphql.schema_builder")]
        private readonly SchemaBuilderInterface $schemas,
        #[Autowire(service: "cache.metadata")]
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Huella del SDL del schema. Armarlo cuesta (más de un segundo en dev):
     * se guarda por compilación del contenedor, que cambia al desplegar código.
     * `$fresca` la vuelve a calcular (comando de despliegue).
     */
    public function esquema(bool $fresca = false): string
    {
        if ($fresca) {
            $this->cache->delete(self::ESQUEMA);
        }

        return $this->cache->get(self::ESQUEMA, fn(): string => hash("sha256", SchemaPrinter::doPrint($this->schemas->getSchema(), ["sortTypes" => true])));
    }

    /**
     * Huella de la configuración de cada entidad. Con `$entidades`, solo esas
     * (null la que ya no tiene configuración); sin, todas las que tienen.
     *
     * @param list<string>|null $entidades
     *
     * @return array<string, ?string>
     */
    public function configuraciones(?array $entidades = null): array
    {
        if ($entidades === []) {
            return [];
        }
        $padre = $this->em->getClassMetadata(EntityConfiguration::class);
        $conexion = $this->em->getConnection();
        $columnaEntidad = $padre->getColumnName("entityClass");
        $sql = sprintf("SELECT * FROM %s", $padre->getTableName());
        $filas = $entidades === null
            ? $conexion->fetchAllAssociative($sql)
            : $conexion->fetchAllAssociative("{$sql} WHERE {$columnaEntidad} IN (?)", [$entidades], [ArrayParameterType::STRING]);

        $id = $padre->getSingleIdentifierColumnName();
        $ids = array_map(static fn(array $fila): int => (int) $fila[$id], $filas);
        $hijas = $this->anidadas($conexion, $padre, $ids);

        $huellas = $entidades === null ? [] : array_fill_keys($entidades, null);
        $fuera = array_flip([$id, $padre->getColumnName("updatedAt")]);
        foreach ($filas as $fila) {
            $datos = array_diff_key($fila, $fuera);
            foreach ($hijas as $nombre => $porPadre) {
                $datos[$nombre] = $porPadre[(int) $fila[$id]] ?? [];
            }
            $huellas[(string) $fila[$columnaEntidad]] = Huellas::de($datos);
        }

        return $huellas;
    }

    /**
     * Por asociación: las filas hijas de cada uno de `$ids` (con sus propias
     * hijas anidadas) o, en las a muchos con tabla intermedia, los ids
     * relacionados.
     *
     * @param list<int> $ids
     *
     * @return array<string, array<int, list<mixed>>>
     */
    private function anidadas(Connection $conexion, ClassMetadata $padre, array $ids): array
    {
        $hijas = [];
        foreach ($this->hijas($padre) as $nombre => $hija) {
            $porPadre = $this->filasHijas($conexion, $hija, $ids);
            $meta = $hija["meta"];
            $idHija = $meta->getSingleIdentifierColumnName();
            $idsHijas = [];
            foreach ($porPadre as $lista) {
                foreach ($lista as $fila) {
                    $idsHijas[] = (int) $fila[$idHija];
                }
            }
            $nietas = $this->anidadas($conexion, $meta, $idsHijas);
            if ($nietas !== []) {
                foreach ($porPadre as $padreId => $lista) {
                    foreach ($lista as $i => $fila) {
                        foreach ($nietas as $nombreNieta => $porHija) {
                            $porPadre[$padreId][$i][$nombreNieta] = $porHija[(int) $fila[$idHija]] ?? [];
                        }
                    }
                }
            }
            $hijas[$nombre] = $porPadre;
        }
        foreach ($this->aMuchos($padre) as $nombre => $tabla) {
            $hijas[$nombre] = $this->idsRelacionados($conexion, $tabla, $ids);
        }

        return $hijas;
    }

    /**
     * Asociaciones a muchos de la configuración cuyo destino también es del
     * cliente (columnas del listado, campos del formulario).
     *
     * @return array<string, array{tabla: string, fk: string, orden: string, meta: ClassMetadata}>
     */
    private function hijas(ClassMetadata $padre): array
    {
        $hijas = [];
        foreach ($padre->getAssociationMappings() as $nombre => $mapping) {
            if (!$mapping->isOneToMany() || !is_a($mapping->targetEntity, ConfiguracionDelCliente::class, true)) {
                continue;
            }
            $meta = $this->em->getClassMetadata($mapping->targetEntity);
            $orden = [];
            foreach ($mapping->orderBy() as $campo => $direccion) {
                $orden[] = $meta->getColumnName($campo) . (strtoupper($direccion) === "DESC" ? " DESC" : "");
            }
            $orden[] = $meta->getSingleIdentifierColumnName();
            $hijas[$nombre] = [
                "tabla" => $meta->getTableName(),
                "fk" => $meta->getSingleAssociationJoinColumnName($mapping->mappedBy),
                "orden" => implode(", ", $orden),
                "meta" => $meta,
            ];
        }

        return $hijas;
    }

    /**
     * Relaciones a muchos con tabla intermedia de la que es dueña la configuración.
     *
     * @return array<string, array{tabla: string, fk: string, otra: string}>
     */
    private function aMuchos(ClassMetadata $padre): array
    {
        $tablas = [];
        foreach ($padre->getAssociationMappings() as $nombre => $mapping) {
            if (!$mapping->isManyToMany() || !$mapping->isOwningSide()) {
                continue;
            }
            $tablas[$nombre] = [
                "tabla" => $mapping->joinTable->name,
                "fk" => $mapping->joinTable->joinColumns[0]->name,
                "otra" => $mapping->joinTable->inverseJoinColumns[0]->name,
            ];
        }

        return $tablas;
    }

    /**
     * @param array{tabla: string, fk: string, otra: string} $tabla
     * @param list<int> $ids
     *
     * @return array<int, list<int>> por id del dueño, ordenados
     */
    private function idsRelacionados(Connection $conexion, array $tabla, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porDuenio = [];
        foreach ($conexion->fetchAllAssociative(
            sprintf("SELECT %s AS duenio, %s AS otro FROM %s WHERE %s IN (?) ORDER BY 1, 2", $tabla["fk"], $tabla["otra"], $tabla["tabla"], $tabla["fk"]),
            [$ids],
            [ArrayParameterType::INTEGER],
        ) as $fila) {
            $porDuenio[(int) $fila["duenio"]][] = (int) $fila["otro"];
        }

        return $porDuenio;
    }

    /**
     * @param array{tabla: string, fk: string, orden: string, meta: ClassMetadata} $hija
     * @param list<int> $ids
     *
     * @return array<int, list<array<string, mixed>>> por id de la configuración
     */
    private function filasHijas(Connection $conexion, array $hija, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $filas = $conexion->fetchAllAssociative(
            sprintf("SELECT * FROM %s WHERE %s IN (?) ORDER BY %s, %s", $hija["tabla"], $hija["fk"], $hija["fk"], $hija["orden"]),
            [$ids],
            [ArrayParameterType::INTEGER],
        );
        $porPadre = [];
        foreach ($filas as $fila) {
            $padre = (int) $fila[$hija["fk"]];
            unset($fila[$hija["fk"]]);
            $porPadre[$padre][] = $fila;
        }

        return $porPadre;
    }
}
