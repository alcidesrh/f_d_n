<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtro a la medida del listado de tarifas (lo que `ColumnaFilter` no
 * cubre; empresa, clase de bus, bus y usuario van por sus argumentos):
 *
 * - `trayectoOrigen` / `trayectoDestino`: IRI o id de un `Enclave`; las
 *   tarifas de los trayectos con ese origen y/o destino;
 * - `horarioDesde` / `horarioHasta` (`HH:mm`): las tarifas con horario que
 *   rige en algún momento del intervalo (si `desde` > `hasta` cruza la
 *   medianoche); con uno solo, a esa hora. Un extremo vacío de la tarifa deja
 *   ese lado abierto, como en `App\Venta\CandidatoTarifa::enHorario`. Las
 *   tarifas sin horario (comodín) no salen, igual que filtrar por empresa no
 *   trae las que no tienen empresa.
 *
 * Todos se cumplen a la vez (AND).
 */
final class BoletoTarifaFilter implements FilterInterface
{
    public const ORIGEN = 'trayectoOrigen';
    public const DESTINO = 'trayectoDestino';
    public const HORARIO_DESDE = 'horarioDesde';
    public const HORARIO_HASTA = 'horarioHasta';

    public function getDescription(string $resourceClass): array
    {
        return [
            self::ORIGEN => ['property' => 'trayecto', 'type' => 'string', 'required' => false, 'description' => 'Enclave de origen del trayecto (IRI o id).'],
            self::DESTINO => ['property' => 'trayecto', 'type' => 'string', 'required' => false, 'description' => 'Enclave de destino del trayecto (IRI o id).'],
            self::HORARIO_DESDE => ['property' => 'horaDesde', 'type' => 'string', 'required' => false, 'description' => 'HH:mm: tarifas que rigen desde esta hora.'],
            self::HORARIO_HASTA => ['property' => 'horaHasta', 'type' => 'string', 'required' => false, 'description' => 'HH:mm: tarifas que rigen hasta esta hora.'],
        ];
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $filtros = $context['filters'] ?? [];
        $alias = $queryBuilder->getRootAliases()[0];

        $origen = self::id($filtros[self::ORIGEN] ?? null);
        $destino = self::id($filtros[self::DESTINO] ?? null);
        if ($origen !== false || $destino !== false) {
            $trayecto = $queryNameGenerator->generateJoinAlias('trayecto');
            $queryBuilder->innerJoin("$alias.trayecto", $trayecto);
            foreach ([[$origen, 'origen'], [$destino, 'destino']] as [$id, $campo]) {
                if ($id === false) {
                    continue;
                }
                if ($id === null) {
                    $queryBuilder->andWhere('1 = 0');
                    continue;
                }
                $p = $queryNameGenerator->generateParameterName($campo);
                $queryBuilder->andWhere("IDENTITY($trayecto.$campo) = :$p")->setParameter($p, $id);
            }
        }

        $desde = self::hora($filtros[self::HORARIO_DESDE] ?? null);
        $hasta = self::hora($filtros[self::HORARIO_HASTA] ?? null);
        if ($desde !== null || $hasta !== null) {
            $this->horario($queryBuilder, $queryNameGenerator, $alias, $desde ?? $hasta, $hasta ?? $desde);
        }
    }

    /**
     * Tarifas cuyo horario `[a, b]` se cruza con `[$desde, $hasta]`. Uno y
     * otro pueden cruzar la medianoche (inicio > fin): el de la tarifa solo
     * si tiene ambos extremos.
     */
    private function horario(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, string $desde, string $hasta): void
    {
        $a = "COALESCE($alias.horaDesde, '00:00:00')";
        $b = "COALESCE($alias.horaHasta, '23:59:59')";
        $x = $queryNameGenerator->generateParameterName('horarioDesde');
        $y = $queryNameGenerator->generateParameterName('horarioHasta');
        $queryBuilder->setParameter($x, $desde)->setParameter($y, $hasta);

        $queryBuilder->andWhere("($alias.horaDesde IS NOT NULL OR $alias.horaHasta IS NOT NULL)");
        $queryBuilder->andWhere($desde <= $hasta
            ? "(($a <= $b AND $a <= :$y AND $b >= :$x) OR ($a > $b AND ($a <= :$y OR $b >= :$x)))"
            // Las dos cruzan la medianoche: siempre se cruzan.
            : "(($a <= $b AND ($b >= :$x OR $a <= :$y)) OR $a > $b)");
    }

    /** Id del IRI (`/api/enclaves/12`) o número; false si no viene, null si no sirve. */
    private static function id(mixed $valor): int|false|null
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        return preg_match('~(?:^|/)(-?\d+)$~', (string) $valor, $m) === 1 ? (int) $m[1] : null;
    }

    /** `HH:mm` (o `HH:mm:ss`) como `HH:mm:ss`; null si no viene o no sirve. */
    private static function hora(mixed $valor): ?string
    {
        if (!\is_string($valor) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $valor, $m) !== 1) {
            return null;
        }

        return \sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
    }
}
