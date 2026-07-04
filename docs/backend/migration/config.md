# Migración de EntityConfiguration

## Visión general

`EntityConfiguration` almacena metadatos sobre cada entidad del sistema: qué campos mostrar en listados, qué campos incluir en formularios, posiciones, etiquetas y atributos. Esto permite que el frontend sea completamente dinámico sin conocimiento previo de la estructura de las entidades.

## Servicio sincronizador

Archivo: `src/Services/EntityConfigSynchronizer.php`

### Funcionamiento

```php
final class EntityConfigSynchronizer {
    public function syncEntity(string $entityClass): void {
        // 1. Buscar config existente o crear nueva
        $config = $this->entityManager->getRepository(EntityConfiguration::class)
            ->findOneBy(['entityClass' => $entityClass]);

        if (!$config) {
            $config = new EntityConfiguration($entityClass);
            $this->entityManager->persist($config);
        }

        // 2. Obtener metadatos de Doctrine para la entidad
        $metadata = $this->entityManager->getClassMetadata('App\\Entity\\' . $entityClass);
        $currentFields = $this->getAllFieldNames($metadata);

        // 3. Sincronizar campos de colección y formulario
        $this->syncCollectionFieldConfig($config, $currentFields);
        $this->syncFormFields($config, $currentFields);

        // 4. Persistir
        $this->entityManager->flush();
    }
}
```

### Detección de campos

`EntityConfigSynchronizer::getAllFieldNames()` usa `ClassMetadata` de Doctrine para detectar:

1. **Campos simples**: columnas directas (string, integer, date, etc.)
2. **Relaciones**: asociaciones (ManyToOne, OneToMany, ManyToMany)

Cada campo se clasifica por tipo:

| Tipo Doctrine | Tipo frontend |
|---------------|---------------|
| `string`, `text` | `text` |
| `integer`, `float` | `number` |
| `date`, `datetime` | `date`, `datetime` |
| `boolean` | `boolean` |
| ManyToOne | `select` |
| OneToMany / ManyToMany | `multiple` |

### Ocultación automática

Campos ocultos por defecto en colecciones:

```php
if (in_array($data[0], ['legacyId', 'apiTokens'])) {
    $this->visible = false;
}
```

Campos ocultos por defecto en formularios:

```php
if (in_array($data[0], ['legacyId', 'apiTokens', 'id', 'createdAt', 'updatedAt'])) {
    $this->visible = false;
}
```

## Resolver de actualización

Archivo: `src/Resolver/UpdateEntityConfigurationFieldsResolver.php`

Permite actualizar la configuración desde GraphQL:

```graphql
mutation {
    updateEntityConfigurationFields(
        input: {
            entityClass: "Boleto"
            formFields: [
                { id: "/api/form_field_configs/1", label: "Cliente", position: 1 }
            ]
            collectionFieldConfig: [
                { id: "/api/collection_field_configs/1", visible: false }
            ]
        }
    ) {
        entityClass
        updatedAt
    }
}
```

## Publicación de cambios

Cuando se actualiza una configuración, `ConfigChangePublisher` envía un evento Mercure:

```php
$this->configChangePublisher->entityConfigChanged($config);
```

Esto notifica al frontend para que refresque la configuración en tiempo real.

## Integración en el pipeline

Durante `app:migrar:todo`, el paso 4 sincroniza todas las entidades automáticamente:

```php
foreach ($metadataFactory->getAllMetadata() as $metadata) {
    if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
        $shortName = $metadata->getReflectionClass()->getShortName();
        if (!in_array($shortName, ['EntityConfiguration', 'CollectionFieldConfig', 'FormFieldConfig'])) {
            $this->configSynchronizer->syncEntity($shortName);
        }
    }
}
```
