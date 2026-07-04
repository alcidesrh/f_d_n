# Análisis crítico del modelo de datos

Este documento analiza el modelo de entidades actual y propone mejoras.

## Fortalezas del modelo actual

1. **STI en Enclave**: La herencia de tabla única para Enclave → Estacion/Parada es apropiada: comparten la misma estructura y las consultas polimórficas son eficientes.
2. **Embeddable Precio**: Usar `Money\Money` vía embeddable evita errores de redondeo y facilita operaciones monetarias.
3. **Separación de IAM**: El modelo de permisos está correctamente aislado del dominio de negocio.
4. **legacy_id**: Columna estándar para trazabilidad con el sistema legacy.
5. **Naming consistente**: Convenciones snake_case y nombres descriptivos.

## Áreas de mejora

### 1. Relaciones débiles en Boleto

**Problema**: `Boleto.recorrido` es nullable y opcional. El recorrido se puede derivar del servicio, pero tenerlo como nullable puede generar inconsistencias.

**Sugerencia**: Hacer `recorrido_id` NOT NULL en boleto o eliminarlo y derivarlo siempre desde `servicio.recorrido`.

```php
// Actual: nullable
#[ORM\ManyToOne]
private ?Recorrido $recorrido = null;

// Sugerido: NOT NULL o remover
#[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: false)]
private Recorrido $recorrido;
```

### 2. RecorridoMatrioska — relación inconsistente

**Problema**: `RecorridoMatrioska.subrecorridos` apunta a `Recorrido` pero el nombre sugiere una colección. Adicionalmente la relación debería ser bidireccional.

**Sugerencia**: Renombrar a `subrecorrido` (singular) o cambiar a `OneToMany` si un recorrido puede tener múltiples subrecorridos del mismo tipo.

### 3. Status como entidad vs. enum

**Problema**: `Status` es una entidad con una sola columna `nombre`, referenciada por FK desde muchas otras entidades. Para un catálogo pequeño (activo/inactivo), una entidad completa añade joins innecesarios.

**Sugerencia**: Evaluar migrar a columna booleana `activo` en entidades que solo necesitan binario, y mantener Status como catálogo expandible para estados más complejos.

### 4. Factura — tipo de cambio inconsistente

**Problema**: `Factura.receptopNit` tiene typo (debería ser `receptorNit`). Adicionalmente, la relación con `Venta` es OneToOne, lo que implica que una venta puede tener solo una factura.

**Sugerencia**: Corregir el typo y evaluar si el modelo necesita OneToMany (una venta → múltiples facturas).

### 5. Índices faltantes

**Problema**: No hay índices explícitos para patrones de consulta comunes:
- Búsqueda ILIKE en nombre/apellido de usuario
- Búsqueda por rango de fechas en venta y servicio
- Búsqueda por legacy_id en boleto y trayecto

**Sugerencia**: Ver [Indexing](indexing.md) para la lista de índices recomendados.

### 6. Normalización de Localidad

**Problema**: `Localidad` tiene relación a `Nacion` pero no hay relación a una entidad de departamento/estado intermedia.

**Sugerencia**: Evaluar si se necesita una jerarquía Localidad → Departamento → Nacion para mejorar la navegación geográfica.

### 7. Piloto en subdominio compartido

**Problema**: `Piloto` aparece en los subdominios Flota y Personal, lo que sugiere que su ubicación en el modelo de subdominios no está clara.

**Sugerencia**: Consolidar Piloto bajo Flota (es un recurso operativo) y crear una vista lógica en Personal.

### 8. Carencia de versioning en configuraciones

**Problema**: `EntityConfiguration` tiene `updatedAt` pero no mantiene un historial de cambios. Cuando se actualiza la configuración, no hay forma de revertir cambios.

**Sugerencia**: Implementar un log de cambios o tabla de versiones para EntityConfiguration.

### 9. Tipo de datos en columnas de precio

**Problema**: `Recorrido.precioClaseA` y `precioClaseB` usan `Money\Money` via embeddable, pero `Tarifa` no existe como entidad en el nuevo modelo (solo legacy). Los precios se manejan directamente en Recorrido.

**Sugerencia**: Evaluar si se necesita una entidad Tarifa independiente para manejar precios por rango de fechas, temporada, o tipo de cliente.

### 10. Carencia de auditoría

**Problema**: Solo `Venta` y `Factura` tienen timestamps de creación/actualización via traits. Otras entidades transaccionales como `Servicio` y `Boleto` carecen de `updated_at`.

**Sugerencia**: Añadir `TimestampableEntityTrait` a todas las entidades transaccionales.

## Resumen de acciones sugeridas

| Prioridad | Acción | Impacto |
|-----------|--------|---------|
| Alta | Añadir índices compuestos y GIN | Rendimiento de consultas |
| Alta | Hacer NOT NULL columnas que siempre deben tener valor | Integridad de datos |
| Media | Corregir typos (receptopNit → receptorNit) | Consistencia |
| Media | Reevaluar modelo de Precios (Tarifa vs Recorrido) | Flexibilidad |
| Media | Añadir updated_at a entidades faltantes | Auditoría |
| Baja | Versioning de EntityConfiguration | Reversibilidad |
| Baja | Migrar Status a booleano en casos simples | Performance |
| Baja | Normalización de Localidad con departamento | Navegación geográfica |
