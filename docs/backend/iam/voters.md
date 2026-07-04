# Voters

El sistema de autorización usa dos voters Symfony que implementan `VoterInterface`. Ambos delegan la decisión final al `PermissionManager`.

## ActionVoter

Archivo: `src/Security/Voter/ActionVoter.php`

Decide acceso basado en códigos de acción en formato `recurso.operacion` (e.g. `boleto.ver`, `usuario.editar`).

### Soporte

El voter solo procesa atributos que contienen un punto (`.`):

```php
protected function supports(string $attribute, mixed $subject): bool {
    if (!is_string($attribute)) {
        return false;
    }
    return str_contains($attribute, '.');
}
```

### Votación

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
    $user = $token->getUser();
    if (!$user instanceof Usuario) return false;
    if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return true;
    return $this->permissionManager->can($user, $attribute);
}
```

### Uso

```php
$this->denyAccessUnlessGranted('boleto.ver');
$this->denyAccessUnlessGranted('usuario.editar', $usuario);
```

## EntityVoter

Archivo: `src/Security/Voter/EntityVoter.php`

Decide acceso a nivel de entidad usando los cuatro CRUD operations estándar: `create`, `read`, `update`, `delete`.

### Constantes

```php
class EntityVoter extends Voter {
    public const CREATE = 'create';
    public const READ   = 'read';
    public const UPDATE = 'update';
    public const DELETE = 'delete';
}
```

### Soporte

Procesa solo atributos que sean uno de los cuatro CRUD strings:

```php
protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, [self::CREATE, self::READ, self::UPDATE, self::DELETE], true);
}
```

### Votación

Convierte el atributo CRUD a un código de acción usando el nombre de la entidad:

```php
$entityName = $this->resolveEntityName($subject);
$actionCode = strtolower($entityName) . '.' . $attribute;
return $this->permissionManager->can($user, $actionCode);
```

Por ejemplo, `$this->denyAccessUnlessGranted(EntityVoter::UPDATE, $boleto)` se traduce a `boleto.update`.

### Resolución del nombre de entidad

```php
private function resolveEntityName(mixed $subject): string {
    if ($subject === null) return 'unknown';
    if (is_string($subject)) {
        $parts = explode('\\', $subject);
        return end($parts);
    }
    if (is_object($subject)) {
        $class = get_class($subject);
        $parts = explode('\\', $class);
        return end($parts);
    }
    return 'unknown';
}
```

## Estrategia de decisión

En `config/packages/security.yaml`:

```yaml
access_decision_manager:
    strategy: unanimous
```

Todos los voters deben coincidir para conceder acceso (estrategia unánime). Si algún voter deniega, el acceso se rechaza.

## Access Control (ACL fijas)

Además de los voters, hay reglas de acceso_control en security.yaml que protegen rutas por rol Symfony:

```yaml
access_control:
    - { path: "^/api/me/permissions", roles: ROLE_USER }
    - { path: "^/api/", roles: ROLE_USER }
    - { path: "^/api/change-password", roles: ROLE_ADMIN }
    - { path: "^/api/users-brief", roles: ROLE_ADMIN }
    - { path: "^/api/entity-record-counts", roles: ROLE_ADMIN }
    - { path: "^/graphql/graphiql", roles: PUBLIC_ACCESS }
    - { path: "^/docs", roles: PUBLIC_ACCESS }
```

## ActionExpressionProvider

Archivo: `src/Security/ActionExpressionProvider.php`

Provee la función `can()` para su uso en expresiones de seguridad Symfony:

```twig
{% if is_granted('can', 'boleto.ver') %}
    <button>Ver boleto</button>
{% endif %}
```
