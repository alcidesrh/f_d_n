# Resolvedores GraphQL

Los resolvedores permiten lógica personalizada para consultas y mutaciones GraphQL.

## CollectionResolver

Archivo: `src/Resolver/CollectionResolver.php`

Resuelve colecciones genéricas de entidades para el frontend dinámico.

```php
final class CollectionResolver implements QueryItemResolverInterface {
    public function __invoke(?object $item, array $context): object {
        $metadata = new Agnostic();
        $metadata->data = (new ArrayCollection(
            $this->entityManagerInterface
                ->getRepository(Doctrine::entityNamespace($context['args']['resource']))
                ->findAll()
        ))->map(fn($v) => [
            'id' => $this->iriConverter->getIriFromResource($v),
            'label' => $v->getLabel()
        ])->toArray();
        return $metadata;
    }
}
```

Propósito: devolver pares `{id: IRI, label: string}` para cualquier entidad, usado por selects y autocompletes en el frontend.

## UserByUsernameResolver

Archivo: `src/Resolver/UserByUsernameResolver.php`

Resuelve un usuario por nombre de usuario para el login.

```php
final class UserByUsernameResolver implements QueryItemResolverInterface {
    public function __invoke($item, array $context): object {
        if ($arg = $context['args']['username'] ?? null) {
            if ($user = $this->userRepository->findOneBy(['id' => $arg]))
                return $user;
        }
        throw new \Exception('No existe el usuario: ' . $arg);
    }
}
```

Registrado como operación GraphQL:

```php
new Query(
    name: 'getByUsername',
    resolver: UserByUsernameResolver::class,
    args: ['username' => ['type' => 'String']],
),
```

## UpdateEntityConfigurationFieldsResolver

Archivo: `src/Resolver/UpdateEntityConfigurationFieldsResolver.php`

Actualiza los campos de configuración de una entidad (visibilidad, posición, etiquetas, etc.).

```php
final class UpdateEntityConfigurationFieldsResolver implements MutationResolverInterface {
    public function __invoke($item, array $context): EntityConfiguration {
        // Cargar EntityConfiguration por entityClass
        // Procesar formFields y collectionFieldConfig
        // Marcar como actualizado
        // Publicar cambio via Mercure
    }
}
```

Registrado como mutation:

```php
new Mutation(
    name: 'updateWithRelations',
    resolver: UpdateEntityConfigurationFieldsResolver::class,
    args: [
        'entityClass' => ['type' => 'String!'],
        'formFields' => ['type' => '[updateFormFieldConfigInput]'],
        'collectionFieldConfig' => ['type' => '[updateCollectionFieldConfigInput]'],
    ]
)
```

## Input types para configuración

Definidos como clases anónimas en los atributos `Mutation`:

```graphql
input updateFormFieldConfigInput {
    id: String!
    label: String
    visible: Boolean
    position: Int
    groupName: String
    attrs: JSON
}

input updateCollectionFieldConfigInput {
    id: String!
    label: String
    visible: Boolean
    position: Int
    sortable: Boolean
    filterable: Boolean
    attrs: JSON
}
```
