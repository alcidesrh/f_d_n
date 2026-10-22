<?php

namespace App\Doctrine;

use App\Entity\Bus;
use App\Entity\BoletoTarifa;
use App\Entity\EsquemaSalida;
use App\Entity\Establecimiento;
use App\Entity\Piloto;
use App\Entity\Salida;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Piso de visibilidad por empresa de las entidades operativas del EM
 * `default` (ADR-015, ADR-039): solo las filas de las empresas donde el
 * usuario tiene alguna asignación vigente, más las compartidas (sin
 * empresa). Lo habilita en cada petición App\EventListener\TenantFilterListener
 * —FrankenPHP en worker mode mantiene vivo el EntityManager y sus filtros—;
 * con alcance sobre todas las empresas, o sin sesión, queda apagado.
 *
 * Es la red gruesa: qué puede hacer con cada fila lo decide su capacidad
 * (`App\Acceso\Autorizacion`). Entidades fuera de esta lista (Enclave,
 * Trayecto, Cliente, Status, ...) son catálogos globales y no se filtran.
 */
final class TenantFilter extends SQLFilter
{
    public const PARAMETRO = 'empresas';

    private const TENANT_ENTITIES = [
        Bus::class,
        Piloto::class,
        Salida::class,
        BoletoTarifa::class,
        EsquemaSalida::class,
        Establecimiento::class,
    ];

    public function addFilterConstraint(
        ClassMetadata $targetEntity,
        string $targetTableAlias,
    ): string {
        if (!in_array($targetEntity->getName(), self::TENANT_ENTITIES, true) || !$this->hasParameter(self::PARAMETRO)) {
            return "";
        }

        $empresas = $this->getParameterList(self::PARAMETRO);

        return $empresas === ""
            ? sprintf("%s.empresa_id IS NULL", $targetTableAlias)
            : sprintf("(%s.empresa_id IN (%s) OR %s.empresa_id IS NULL)", $targetTableAlias, $empresas, $targetTableAlias);
    }
}
