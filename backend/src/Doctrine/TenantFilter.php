<?php

namespace App\Doctrine;

use App\Entity\Bus;
use App\Entity\BoletoTarifa;
use App\Entity\Piloto;
use App\Entity\Recorrido;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Aísla por empresa las entidades operativas del EM `default`. Se habilita/deshabilita
 * en cada request desde App\EventListener\TenantFilterListener según la empresa del
 * Usuario autenticado — necesario porque FrankenPHP en worker mode mantiene el
 * EntityManager (y por tanto el estado de los filtros) vivo entre peticiones.
 *
 * Entidades fuera de esta lista (Enclave, Trayecto, Cliente, Status, ...) son
 * catálogos globales compartidos entre empresas y no se filtran.
 */
final class TenantFilter extends SQLFilter
{
    private const TENANT_ENTITIES = [
        Bus::class,
        Piloto::class,
        Recorrido::class,
        BoletoTarifa::class,
    ];

    public function addFilterConstraint(
        ClassMetadata $targetEntity,
        string $targetTableAlias,
    ): string {
        if (!in_array($targetEntity->getName(), self::TENANT_ENTITIES, true)) {
            return "";
        }

        if (!$this->hasParameter("empresaId")) {
            return "";
        }

        return sprintf(
            "%s.empresa_id = %s",
            $targetTableAlias,
            $this->getParameter("empresaId"),
        );
    }
}
