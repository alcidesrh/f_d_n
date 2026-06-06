# AGENTS.md

## Project Overview

This project is a Symfony application exposing a GraphQL API backed by PostgreSQL and running in Docker Compose.

Main stack:

- PHP 8.3+
- Symfony 7+
- GraphQL
- Doctrine ORM
- PostgreSQL
- Docker Compose

---

## Development Environment

All commands must be executed inside Docker containers unless explicitly stated otherwise.

### Start Environment

```bash
docker compose up -d
```

### Stop Environment

```bash
docker compose down
```

### Rebuild Containers

```bash
docker compose up -d --build
```

### Enter Backend Container

```bash
docker compose exec backend sh
```

---

## Important Services

| Service  | Purpose              |
| -------- | -------------------- |
| backend  | Symfony API          |
| frontend | Frontend application |
| database | PostgreSQL           |

Never rename services without updating every Docker, Symfony and GraphQL reference.

---

## Symfony Rules

### Controllers

- Keep controllers thin.
- Business logic must not live in controllers.
- Delegate work to services.

### Services

- Prefer constructor injection.
- Do not use service locators.
- Avoid static services.

### Configuration

- Prefer configuration through environment variables.
- Do not hardcode URLs, credentials or ports.

### Events

- Prefer Symfony events when communication between modules is needed.
- Avoid tight coupling between bounded contexts.

---

## Doctrine Rules

### Entities

- Use PHP attributes.
- Do not use Doctrine annotations.
- Prefer typed properties.
- Prefer DateTimeImmutable.

Example:

```php
#[ORM\Column]
private string $name;
```

### Repositories

- Keep repositories focused on persistence.
- Do not place business logic in repositories.

### Migrations

When changing entities:

1. Generate migration.
2. Review generated SQL.
3. Never modify already executed migrations.

Generate migration:

```bash
docker compose exec backend php bin/console doctrine:migrations:diff
```

Execute migration:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

---

## GraphQL Rules

### Schema

- Keep schema explicit.
- Avoid exposing Doctrine entities directly.
- Use DTOs when appropriate.

### Resolvers

- Resolvers must remain thin.
- Business logic belongs in services.

### N+1 Queries

When loading related entities:

- Prefer DataLoader patterns.
- Avoid N+1 database queries.

### Breaking Changes

Do not:

- Rename fields.
- Remove fields.
- Change scalar types.

Unless explicitly requested.

---

## Coding Standards

### PHP

- Follow PSR-12.
- Use strict types.

Every PHP file should start with:

```php
declare(strict_types=1);
```

### Naming

Classes:

```php
UserService
CreateUserMutation
UserRepository
```

Methods:

```php
createUser()
findByEmail()
```

### Avoid

- Global state
- Static mutable state
- Hidden side effects
- Duplicate code

---

## Testing

Whenever code changes:

### Unit Tests

```bash
docker compose exec backend php bin/phpunit
```

### Specific Test

```bash
docker compose exec backend php bin/phpunit tests/Path/To/Test.php
```

New business logic should include tests.

---

## Static Analysis

Before considering work complete:

```bash
docker compose exec backend php vendor/bin/phpstan analyse
```

If PHP CS Fixer exists:

```bash
docker compose exec backend php vendor/bin/php-cs-fixer fix --dry-run
```

---

## Performance Rules

Avoid:

- Loading entire tables.
- Unbounded collections.
- N+1 queries.
- Expensive queries inside loops.

Prefer:

- Pagination.
- Explicit projections.
- Indexed searches.
- Batch loading.

---

## Security Rules

Never:

- Commit secrets.
- Commit `.env.local`.
- Log passwords.
- Log JWT secrets.
- Disable authentication without explicit request.

Treat all external input as untrusted.

Validate and sanitize user input.

---

## Docker Rules

Do not:

- Change exposed ports without request.
- Rename containers without updating references.
- Modify database volumes without explicit request.

Prefer Docker Compose commands over host commands.

---

## Before Completing Any Task

Checklist:

- Code compiles.
- Tests pass.
- PHPStan passes.
- GraphQL schema remains valid.
- Migration created if entities changed.
- No secrets added.
- No unrelated files modified.

---

## Files Requiring Extra Caution

- compose.yaml
- docker-compose.yml
- .env
- .env.local
- config/packages/\*
- config/services.yaml
- config/routes/\*
- migrations/\*
- graphql schema definitions

Review changes carefully before modifying these files.

---

## Agent Behavior

When implementing changes:

1. Read existing code before modifying it.
2. Follow existing architectural patterns.
3. Prefer minimal changes over large refactors.
4. Do not introduce new dependencies unless necessary.
5. Explain architectural tradeoffs when relevant.
6. Do not rewrite working code without a reason.
7. Preserve backwards compatibility whenever possible.
