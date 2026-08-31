# Optimización de consultas

## Patrones de consulta

### Consultas más frecuentes

1. **Login**: `SELECT * FROM usuario WHERE username = ?`
2. **Listar boletos**: `SELECT * FROM boleto ORDER BY id LIMIT ? OFFSET ?`
3. **Boletos de una salida**: `SELECT * FROM boleto WHERE salida_id = ?`
4. **Salidas por fecha**: `SELECT * FROM salida WHERE fecha::date = ?`
5. **Ventas por usuario**: `SELECT * FROM venta WHERE usuario_id = ? ORDER BY created_at DESC`
6. **Búsqueda de usuarios**: Búsqueda OR con ILIKE en múltiples campos

### Optimización por tipo de consulta

**Búsquedas exactas** (login, FK lookups):
- Índices B-tree estándar
- Consultas con parámetros (evitar inyección SQL, mejorar plan cache)

**Búsquedas de rango** (fechas):
- Índices B-tree en columnas de fecha
- Consultas con `BETWEEN` o `>=` / `<=`

**Búsquedas textuales** (ILIKE):
- Índices GIN con trigramas (`pg_trgm`)
- Alternativa: índices `text_pattern_ops`

**Ordenamiento**:
- Índices que soporten ORDER BY (evitar sort en memoria)
- Ordenamiento por primary key cuando sea posible

## Batch processing

Durante la migración, se usan lotes para evitar agotar memoria:

```php
// Migrador: procesa N salidas a la vez
private $salidas = 100; // Default

// Limpiar debug data holder entre lotes
$this->resetDebugDataHolder();
```

## Evitar N+1

Ver [N+1 prevention](../graphql/nplus1.md) para estrategias detalladas.

## Consultas lentas

Monitorear con:
1. Symfony Profiler (entorno dev)
2. `log_min_duration_statement` en PostgreSQL
3. Blackfire.io para profiling de producción

```sql
-- En PostgreSQL: monitorear consultas lentas
SELECT query, calls, total_time, mean_time
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;
```

## Recomendaciones

1. **Siempre paginar** colecciones grandes (boleto, venta, salida)
2. **Usar JOINs con addSelect** en lugar de lazy loading
3. **Evitar SELECT *** cuando solo se necesitan campos específicos
4. **Usar índices compuestos** para consultas multi-campo
5. **Monitorear con EXPLAIN ANALYZE** las consultas lentas
