# Makefile — Other

| Comando | Descripción |
|---------|-------------|
| `make b` | Builds the images (php + caddy) |
| `make cc` | Clear the cache (inside container) |
| `make d` | Stop the docker hub |
| `make db_port` | up with xdebug |
| `make entity` | Create/modify an entity |
| `make entity-setup` | Sync dynamic entity config metadata |
| `make migrar-entities` | make migrar-entities entities='Empresa Localidad Estacion Piloto Marca Bus Asiento cliente usuario trayecto tarifa' |
| `make migrate` | Run pending migrations |
| `make migration` | Create a new migration |
| `make reset-db` | Reset database (drop, create, migrations, then migrate old data) |
| `make sincronizar` | Sync new tickets from old FDN system |
| `make test` | Run all tests |
| `make testf` | Run tests by name filter (usage: make testf F="testMethodName") |
| `make warmup` | Warmup the cache (inside container) |