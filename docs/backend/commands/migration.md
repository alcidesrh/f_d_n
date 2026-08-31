# Comandos de migración

## app:migrar

Archivo: `src/Command/MigrarCommand.php`

```bash
php bin/console app:migrar [c] [--r]
```

Migra boletos del sistema FDN legacy al nuevo. Cada ejecución procesa `c` servicios (salidas).

| Argumento | Descripción | Default |
|-----------|-------------|---------|
| `c` | Número de servicios a migrar | 100 |
| `--r` | Limpia la base de datos nueva antes de migrar | false |

## app:migrar:todo

Archivo: `src/Command/MigrarTodoCommand.php`

```bash
php bin/console app:migrar:todo [options]
```

Ejecuta la migración completa. Ver [Pipeline](../migration/pipeline.md) para detalle de pasos.

| Opción | Descripción |
|--------|-------------|
| `--clean` | DROP + recreate schema |
| `--skip-estaticos` | Salta entidades estáticas |
| `--skip-iam` | Salta migración IAM |
| `--skip-config` | Salta EntityConfiguration |
| `--salidas=N` | Cantidad de salidas (default: 100) |
| `--entities=[]` | Entidades estáticas específicas |

## app:migrar:estaticos

Archivo: `src/Command/MigrarEstaticosCommand.php`

```bash
php bin/console app:migrar:estaticos
```

Migra datos estáticos: empresa, estación, bus, asiento, cliente, usuario, trayecto, tarifa.

## app:migrar:iam

Archivo: `src/Command/MigrarIAMCommand.php`

```bash
php bin/console app:migrar:iam
```

Migra roles, permisos y acciones del sistema FDN al nuevo modelo IAM.
