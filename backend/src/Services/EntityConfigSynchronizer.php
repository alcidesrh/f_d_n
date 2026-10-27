<?php

namespace App\Services;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\FormFieldConfig;
use App\Entity\ListConfiguration;
use App\Entity\RevisionMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza la configuración de cada entidad con su mapeo de Doctrine. Los
 * navegadores se enteran de lo que cambie por la revisión de la metadata que
 * se publica al terminar la petición o el comando (ADR-036).
 */
final class EntityConfigSynchronizer
{
    /** No se configuran: son la configuración misma y sus revisiones. */
    private const EXCLUIDAS = [EntityConfiguration::class, ListConfiguration::class, CollectionFieldConfig::class, FormFieldConfig::class, RevisionMetadata::class];

    /** Nombre de la configuración del listado que se crea con la entidad (la por defecto, sin roles). */
    public const POR_DEFECTO = 'Por defecto';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Entidades que se configuran, por nombre corto (`Bus`).
     *
     * @return list<string>
     */
    public function entidades(): array
    {
        $entidades = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || \in_array($metadata->getName(), self::EXCLUIDAS, true)) {
                continue;
            }
            $entidades[] = $metadata->getReflectionClass()->getShortName();
        }

        return $entidades;
    }

    public function syncEntity(string $entityClass): EntityConfiguration
    {
        $config = $this->entityManager->getRepository(EntityConfiguration::class)
            ->findOneBy(['entityClass' => $entityClass]);
        if (!$config) {
            $config = new EntityConfiguration($entityClass);
            $this->entityManager->persist($config);
        }

        $metadata = $this->entityManager->getClassMetadata('App\\Entity\\' . $entityClass);
        $currentFields = self::getAllFieldNames($metadata);

        if ($config->getListConfigurations()->isEmpty()) {
            $this->entityManager->persist(new ListConfiguration($config, self::POR_DEFECTO));
        }
        // Cada configuración del listado (ADR-043) tiene todas las columnas.
        foreach ($config->getListConfigurations() as $listado) {
            $this->syncCollectionFieldConfig($listado, $currentFields);
        }
        $this->syncFormFields($config, $currentFields);

        $this->entityManager->flush();

        return $config;
    }

    public static function getAllFieldNames(ClassMetadata $metadata): array
    {

        // Campos simples (columnas en la tabla user)
        $camposSimples = $metadata->getFieldNames();
        // Ejemplo típico: ['id', 'email', 'username', 'createdAt', 'isActive']

        // Relaciones (propiedades que son entidades o colecciones)
        $relaciones = $metadata->getAssociationNames();
        // Ejemplo típico: ['profile', 'roles', 'posts', 'address', 'favoriteProducts']

        // Combinado: todos los "atributos mapeados"
        $todosLosNombres = array_merge($camposSimples, $relaciones);

        // Para mayor detalle puedes hacer:
        $detalle = [];
        foreach ($camposSimples as $campo) {
            // Subcampos de un embeddable (`precio.monto`): no existen en la API.
            if (\in_array($campo, ['legacyId', 'password', 'foto', 'apiTokens']) || str_contains($campo, '.')) {
                continue;
            }
            $mapping = $metadata->getFieldMapping($campo);
            $detalle[] = [$campo, match ($mapping->type) {
                'string', 'text'  => 'text',
                'integer', 'float' => 'number',
                default => $mapping->type,
            }];
        }

        foreach ($relaciones as $relacion) {
            $targetClass = $metadata->getAssociationTargetClass($relacion);
            $assocMapping = $metadata->getAssociationMapping($relacion);
            $detalle[] = [
                $relacion,
                match ($assocMapping->type()) {
                    \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE   => 'select',
                    \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_ONE  => 'select',
                    \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_MANY  => 'multiple',
                    \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY => 'multiple',
                    default => 'Desconocido',
                },
                substr($targetClass, strrpos($targetClass, '\\') + 1)
            ];
        }
        return [...$detalle, ...self::camposDeLaApi($metadata->getReflectionClass(), array_column($detalle, 0))];
    }

    /**
     * Campos de la API sin columna propia: getters marcados con `#[ApiProperty]`
     * (legibles) que devuelven un escalar, p. ej. `BoletoTarifa::getImporte()`
     * en lugar de su embeddable `precio`. La marca explícita evita sumar
     * cualquier getter de la entidad.
     *
     * @param list<string> $yaIncluidos
     * @return list<array{0: string, 1: string}>
     */
    private static function camposDeLaApi(\ReflectionClass $clase, array $yaIncluidos): array
    {
        $campos = [];
        foreach ($clase->getMethods(\ReflectionMethod::IS_PUBLIC) as $metodo) {
            if ($metodo->isStatic() || $metodo->getNumberOfRequiredParameters() > 0 || !preg_match('/^(?:get|is)(.+)$/', $metodo->getName(), $m)) {
                continue;
            }
            $atributo = $metodo->getAttributes(ApiProperty::class)[0] ?? null;
            if ($atributo === null || $atributo->newInstance()->isReadable() === false) {
                continue;
            }
            $tipo = $metodo->getReturnType();
            $nombre = lcfirst($m[1]);
            if (!$tipo instanceof \ReflectionNamedType || \in_array($nombre, $yaIncluidos, true)) {
                continue;
            }
            $kind = match ($tipo->getName()) {
                'string' => 'text',
                'int', 'float' => 'number',
                'bool' => 'boolean',
                default => null,
            };
            if ($kind !== null) {
                $campos[] = [$nombre, $kind];
            }
        }

        return $campos;
    }


    private function syncCollectionFieldConfig(ListConfiguration $config, array $currentFields): void
    {
        $existing = [];
        foreach ($config->getCollectionFieldConfig() as $field) {
            $existing[$field->getField()] = $field;
        }

        foreach ($currentFields as $data) {
            if (!isset($existing[$data[0]])) {
                $collectionFieldConfig = new CollectionFieldConfig($data);
                $this->entityManager->persist($collectionFieldConfig);
            } else {
                $collectionFieldConfig = $existing[$data[0]];
                $collectionFieldConfig->setData($data);
            }
            $config->addCollectionFieldConfig($collectionFieldConfig);
        }
        // Los campos que ya no están en el mapeo no se acumulan (p. ej. `Salida.trayecto`, ADR-030).
        foreach (array_diff_key($existing, array_flip(array_column($currentFields, 0))) as $huerfano) {
            $config->getCollectionFieldConfig()->removeElement($huerfano);
            $this->entityManager->remove($huerfano);
        }
        $config->getEntityConfig()->orderFields($config->getCollectionFieldConfig());
    }

    private function syncFormFields(EntityConfiguration $config, array $currentFields): void
    {
        $existing = [];
        foreach ($config->getFormFields() as $field) {
            $existing[$field->getField()] = $field;
        }

        foreach ($currentFields as $data) {

            if (!isset($existing[$data[0]])) {
                $formField = new FormFieldConfig($data);
                $this->entityManager->persist($formField);
            } else {
                $formField = $existing[$data[0]];
                $formField->setData($data);
            }

            $config->addFormField($formField);

        }
        foreach (array_diff_key($existing, array_flip(array_column($currentFields, 0))) as $huerfano) {
            $config->getFormFields()->removeElement($huerfano);
            $this->entityManager->remove($huerfano);
        }
        $config->orderFields($config->getFormFields());
    }
}
