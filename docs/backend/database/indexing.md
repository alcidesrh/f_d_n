# Estrategia de índices

## Índices actuales

Doctrine genera automáticamente índices para:
- Primary keys (PK) en todas las tablas
- Foreign keys (FK) — Doctrine crea índices implícitos en columnas FK
- Columnas `unique` — índice único

La estrategia actual se basa en estos índices generados automáticamente.

## Columnas comúnmente filtradas

Basado en los filtros de API Platform y las consultas más frecuentes:

| Entidad | Columna | Tipo de búsqueda | Recomendación |
|---------|---------|-----------------|---------------|
| Usuario | `username` | Búsqueda exacta (login) | Índice único (ya existe) |
| Usuario | `nombre`, `apellido`, `email` | Búsqueda parcial (OR filter) | Índice GIN con trigramas |
| Boleto | `legacy_id` | Búsqueda exacta (migración) | Índice único |
| Boleto | `servicio_id` | FK lookup (boletos de un servicio) | Índice (ya existe por FK) |
| Boleto | `cliente_id` | FK lookup | Índice |
| Servicio | `fecha` | Rango de fechas | Índice B-tree |
| Servicio | `legacy_id` | Búsqueda exacta | Índice único |
| Trayecto | `legacy_id` | Búsqueda exacta (migración) | Índice único |
| Trayecto | `origen_id`, `destino_id` | FK lookup | Índices compuestos |
| Recorrido | `trayecto_id`, `empresa_id` | FK composite lookup | Índice compuesto |
| Venta | `usuario_id` | FK lookup (ventas de un usuario) | Índice |
| Venta | `created_at` | Rango de fechas | Índice B-tree |
| EntityConfiguration | `entityClass` | Búsqueda exacta | Índice único (ya existe) |

## Patrones de consulta comunes

### Consulta principal: boletos por servicio

```sql
SELECT b.* FROM boleto b WHERE b.servicio_id = ? ORDER BY b.id
```

### Consulta de ventas por usuario y rango

```sql
SELECT v.* FROM venta v WHERE v.usuario_id = ? AND v.created_at BETWEEN ? AND ?
```

### Consulta de servicios por fecha

```sql
SELECT s.* FROM servicio s WHERE s.fecha::date = ? ORDER BY s.fecha
```

### Búsqueda de usuarios

```sql
SELECT u.* FROM usuario u 
WHERE u.nombre ILIKE '%termino%' OR u.apellido ILIKE '%termino%' OR u.email ILIKE '%termino%'
```

## Índices recomendados

```sql
-- Búsqueda eficiente de boletos por legacy_id
CREATE UNIQUE INDEX IF NOT EXISTS idx_boleto_legacy ON boleto(legacy_id) WHERE legacy_id IS NOT NULL;

-- Búsqueda de servicios por fecha
CREATE INDEX IF NOT EXISTS idx_servicio_fecha ON servicio(fecha);

-- Búsqueda textual en usuario (trigramas)
CREATE INDEX IF NOT EXISTS idx_usuario_nombre_trgm ON usuario USING GIN (nombre gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_usuario_apellido_trgm ON usuario USING GIN (apellido gin_trgm_ops);

-- Consultas compuestas en venta
CREATE INDEX IF NOT EXISTS idx_venta_usuario_fecha ON venta(usuario_id, created_at);

-- Búsqueda de trayecto por enclaves
CREATE INDEX IF NOT EXISTS idx_trayecto_origen_destino ON trayecto(origen_id, destino_id);

-- Búsqueda de recorrido compuesta
CREATE INDEX IF NOT EXISTS idx_recorrido_trayecto_empresa ON recorrido(trayecto_id, empresa_id);
```

## Extensiones de PostgreSQL necesarias

Para índices GIN con trigramas:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

## Monitoreo de consultas lentas

Ver [Performance > Queries](../performance/queries.md) para detalles sobre monitoreo y optimización de consultas.

## Consideraciones

1. Los índices compuestos deben priorizar columnas con alta selectividad primero.
2. En tablas transaccionales (boleto, venta), los índices deben balancearse con el rendimiento de escritura.
3. Para la migración de datos, se recomienda dropear índices no críticos antes de la carga masiva y recrearlos después.
4. La función `CAST` en DQL permite conversiones de tipos en consultas Doctrine.
