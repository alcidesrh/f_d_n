<?php

declare(strict_types=1);

namespace App\Acceso\Api;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Acceso\Autorizacion;
use App\Acceso\Catalogo\CatalogoRecursos;
use Doctrine\ORM\QueryBuilder;

/**
 * Los listados de la API genérica de una entidad con empresa traen solo las
 * filas de las empresas donde el usuario tiene la capacidad de verla, más
 * las compartidas (sin empresa). ADR-039.
 */
final class AlcanceEnColecciones implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly CatalogoRecursos $recursos,
        private readonly Autorizacion $autorizacion,
    ) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $d = $this->recursos->de($resourceClass);
        if ($d === null || $d->empresa === null || $d->ver === null) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];
        $partes = $d->empresa === '@id' ? ['id'] : explode('.', $d->empresa);
        $campo = array_pop($partes);
        foreach ($partes as $relacion) {
            $join = $queryNameGenerator->generateJoinAlias($relacion);
            $queryBuilder->leftJoin("$alias.$relacion", $join);
            $alias = $join;
        }

        [$condicion, $parametros] = $this->autorizacion->alcance($d->ver)
            ->condicion("$alias.$campo", null, true, $queryNameGenerator->generateParameterName('alcance'));
        if ($condicion === '1 = 1') {
            return;
        }
        // Por una relación, la empresa viene de la fila relacionada. Si el LEFT JOIN no la encuentra
        // (el filtro de tenant la oculta, ADR-015), el campo queda a null y la rama "compartido"
        // (`IS NULL`) la dejaría pasar: se exige que la fila exista. Sin relación, un null es compartido.
        if ($partes !== []) {
            $queryBuilder->andWhere("$alias.id IS NOT NULL");
        }
        $queryBuilder->andWhere($condicion);
        foreach ($parametros as $nombre => $valor) {
            $queryBuilder->setParameter($nombre, $valor);
        }
    }
}
