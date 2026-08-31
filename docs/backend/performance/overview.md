# Performance

## Consideraciones generales

El backend de FDN Transportes maneja datos transaccionales (boletos, ventas, salidas) que pueden crecer significativamente. Las principales consideraciones de rendimiento son:

| Aspecto | Riesgo | Estrategia |
|---------|--------|------------|
| Colecciones grandes | Paginación lenta | Paginación tipo `page` con límites |
| N+1 queries | Múltiples consultas DB | JOINs explícitos, EAGER loading selectivo |
| Migración masiva | Timeout, memoria | Batch processing, transacciones por lote |
| Carga concurrente | Contención de recursos | Connection pooling, índices |
| Schema dinámico | Consultas no optimizadas | Cache de metadata, índices GIN |

## Principios

1. **Paginación obligatoria** en listados de entidades transaccionales
2. **Batch processing** para operaciones masivas (migración)
3. **Cache** de resultados de Doctrine en producción
4. **Índices** en columnas de búsqueda frecuente
5. **Monitoreo** con Symfony Profiler y Blackfire
