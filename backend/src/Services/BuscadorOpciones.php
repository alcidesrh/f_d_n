<?php

declare(strict_types=1);

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Opciones `{ value: IRI, label }` de una entidad con muchos registros, de a
 * pocas: las que coinciden con lo tecleado (autocompletado) o las de unos ids
 * concretos (la etiqueta de lo ya elegido). Nunca carga la tabla entera.
 *
 * La búsqueda no conoce la etiqueta de cada entidad (se calcula en PHP), así
 * que mira los campos de texto de la entidad y los de sus relaciones a uno:
 * `Trayecto` se encuentra por su nombre y por el de su origen y su destino.
 * Cada palabra tiene que aparecer en alguno de esos campos ("guate flor"
 * encuentra "Guatemala → Flores"); un número también busca por id.
 */
final class BuscadorOpciones
{
    public const LIMITE = 20;
    public const LIMITE_MAXIMO = 100;

    /** Campos que no se buscan aunque sean texto (secretos). */
    private const OCULTOS = '/pass|token|secret|salt|hash/i';

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    /**
     * @param class-string $clase
     * @return list<object>
     */
    public function buscar(string $clase, string $texto, int $limite = self::LIMITE): array
    {
        $limite = max(1, min($limite, self::LIMITE_MAXIMO));
        $metadata = $this->entityManager->getClassMetadata($clase);
        $qb = $this->entityManager->createQueryBuilder()->select('e')->from($clase, 'e');

        $campos = self::campos($metadata, 'e');
        foreach (self::relacionesATexto($metadata) as $i => [$relacion, $destino]) {
            $alias = 'r' . $i;
            // También se seleccionan: la etiqueta suele leerlas (sin N+1).
            $qb->leftJoin('e.' . $relacion, $alias)->addSelect($alias);
            $campos = [...$campos, ...self::campos($this->entityManager->getClassMetadata($destino), $alias)];
        }

        foreach (self::palabras($texto) as $i => $palabra) {
            $condiciones = array_map(fn(string $campo) => "LOWER($campo) LIKE :p$i", $campos);
            if (ctype_digit($palabra) && $metadata->hasField('id')) {
                $condiciones[] = "e.id = :n$i";
                $qb->setParameter("n$i", (int) $palabra);
            }
            if (!$condiciones) {
                return [];
            }
            $qb->andWhere(implode(' OR ', $condiciones));
            if ($campos) {
                $qb->setParameter("p$i", '%' . addcslashes(mb_strtolower($palabra), '%_\\') . '%');
            }
        }

        $registros = $qb->orderBy('e.id', 'ASC')->setMaxResults($limite)->getQuery()->getResult();
        usort($registros, fn($a, $b) => strnatcasecmp(self::etiqueta($a), self::etiqueta($b)));

        return $registros;
    }

    /**
     * @param class-string $clase
     * @param list<int|string> $ids
     * @return list<object>
     */
    public function porIds(string $clase, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id !== '' && $id !== null)));
        if (!$ids) {
            return [];
        }

        return $this->entityManager->getRepository($clase)->findBy(['id' => array_slice($ids, 0, self::LIMITE_MAXIMO)]);
    }

    /** Palabras de lo tecleado (sin repetir, a lo sumo cinco). @return list<string> */
    public static function palabras(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $palabras = array_filter($palabras, fn(string $p) => $p !== '→' && $p !== '-');

        return array_slice(array_values(array_unique($palabras)), 0, 5);
    }

    /** Campos de texto buscables de una clase, como rutas DQL (`e.nombre`). @return list<string> */
    public static function campos(ClassMetadata $metadata, string $alias): array
    {
        $campos = [];
        foreach ($metadata->getFieldNames() as $campo) {
            $tipo = $metadata->getTypeOfField($campo);
            if (\in_array($tipo, ['string', 'text', 'ascii_string'], true) && !preg_match(self::OCULTOS, $campo)) {
                $campos[] = "$alias.$campo";
            }
        }

        return $campos;
    }

    /** Relaciones a uno (con su clase destino) cuyos campos de texto también se buscan. @return list<array{string, class-string}> */
    public static function relacionesATexto(ClassMetadata $metadata): array
    {
        $relaciones = [];
        foreach ($metadata->getAssociationMappings() as $nombre => $mapeo) {
            if ($metadata->isSingleValuedAssociation($nombre) && $metadata->isAssociationWithSingleJoinColumn($nombre)) {
                $relaciones[] = [$nombre, $mapeo['targetEntity']];
            }
        }

        return $relaciones;
    }

    private static function etiqueta(object $registro): string
    {
        return method_exists($registro, 'getLabel') ? (string) $registro->getLabel() : '';
    }
}
