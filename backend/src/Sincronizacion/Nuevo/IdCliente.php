<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\Cliente;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

/**
 * Id de un cliente nuevo: el que le puso `AltaCliente` (el del legado) o, con
 * la sincronización apagada, el siguiente de su secuencia. Sin estado: Doctrine
 * lo guarda con la metadata en caché.
 */
final class IdCliente extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, ?object $entity): int
    {
        return (int) ($em->getClassMetadata(Cliente::class)->getFieldValue($entity, "id")
            ?? $em->getConnection()->fetchOne("SELECT nextval(pg_get_serial_sequence('cliente', 'id'))"));
    }
}
