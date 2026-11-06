<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\Cliente;
use App\Sincronizacion\Interruptor;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Psr\Log\LoggerInterface;

/**
 * El legado da el id de los clientes mientras dura la mudanza (ADR-042): un
 * cliente es el mismo id en los dos sistemas. Al persistir uno nuevo se crea
 * primero allá, sin importar por dónde llegue (venta, página web, GraphQL).
 * Si aquí no llega a guardarse, allá queda un cliente sin uso.
 *
 * Con la sincronización apagada se usa la secuencia de aquí y se anota en
 * `ClientesLocales`: al encender tomará su id del legado.
 *
 * Doctrine no deja poner el id en una entidad con identidad: `Cliente` usa
 * `IdCliente` mientras exista este módulo. La columna sigue siendo identidad.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: ToolEvents::postGenerateSchemaTable)]
class AltaCliente
{
    public function __construct(
        private readonly Interruptor $interruptor,
        private readonly EscritorLegado $escritor,
        private readonly ClientesLocales $locales,
        private readonly LoggerInterface $logger,
    ) {}

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        if ($metadata->getName() === Cliente::class) {
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_CUSTOM);
            $metadata->setIdGenerator(new IdCliente());
        }
    }

    /** Para el esquema la columna sigue siendo identidad: los diff de migraciones no la tocan. */
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args): void
    {
        if ($args->getClassMetadata()->getName() === Cliente::class) {
            $args->getClassTable()->getColumn("id")->setAutoincrement(true);
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $cliente = $args->getObject();
        if (!$cliente instanceof Cliente || !$this->interruptor->activo()) {
            return;
        }
        if ($this->interruptor->soloTraer()) {
            throw new VentaRechazada("En modo «solo traer» no se crean clientes: el id lo da el legado.", "sincronizacion_solo_traer", 409);
        }
        $id = $this->enLegado($cliente);
        if ($args->getObjectManager()->getConnection()->fetchOne("SELECT 1 FROM cliente WHERE id = :id", ["id" => $id]) !== false) {
            throw new VentaRechazada("Ya existe un cliente con ese NIT, nombre y documento.", "cliente_existente", 409, ["cliente" => $id]);
        }
        $cliente->setId($id);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $cliente = $args->getObject();
        if ($cliente instanceof Cliente && $this->interruptor->instalada() && !$this->interruptor->activo()) {
            $this->locales->anotar((int) $cliente->getId());
        }
    }

    private function enLegado(Cliente $c): int
    {
        try {
            return $this->escritor->altaCliente(EscritorLegado::datosDeFila([
                "nombre" => $c->getNombre(),
                "apellido" => $c->getApellido(),
                "nit" => $c->getNit(),
                "email" => $c->getEmail(),
                "telefono" => $c->getTelefono(),
                "direccion" => $c->getDireccion(),
                "numero_documento" => $c->getNumeroDocumento(),
                "tipo_documento_id" => $c->getTipoDocumento()?->getId(),
                "nacionalidad_id" => $c->getNacionalidad()?->getId(),
            ]));
        } catch (\Throwable $e) {
            $this->logger->error("Sincronización: el legado no creó el cliente: {error}", ["error" => $e->getMessage(), "exception" => $e]);
            $this->escritor->cerrarConexion();

            throw new VentaRechazada("No se pudo registrar el cliente en el sistema anterior. Intente de nuevo en un momento.", "sincronizacion_legado", 503, [], $e);
        }
    }
}
