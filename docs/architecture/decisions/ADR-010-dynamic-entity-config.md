# ADR-010: Configuración dinámica de entidades vía metadatos

**Estado:** Aceptada

## Contexto

El sistema de CRUD dinámico (ADR-004) permite generar automáticamente listas y formularios para cualquier entidad. Sin embargo, no todas las entidades deben mostrar todos sus campos, y la presentación de cada campo (orden, etiqueta, tipo de input, visibilidad) puede variar según el contexto.

Se necesita un mecanismo para que el backend exponga metadatos de configuración por entidad que el frontend pueda consumir sin conocimiento previo de la estructura de cada entidad. Esta configuración debe ser editable por administradores del sistema sin necesidad de desplegar código nuevo.

## Decisión

Se implementa un sistema de **configuración dinámica de entidades** basado en metadatos persistentes:

1. **Entidades de configuración**:
   - `EntityConfiguration`: representa una entidad del dominio. Almacena el nombre de la clase PHP (`entityClass`), un ícono asociado y la fecha de última actualización.
   - `CollectionFieldConfig`: configuración de visualización en listas (posición, etiqueta, visible, ancho).
   - `FormFieldConfig`: configuración de formularios (posición, etiqueta, visible, requerido, tipo de input).
   - `FieldConfig`: configuración transversal compartida entre listas y formularios.

2. **Mecanismo de sincronización**:
   - `SyncEntityConfigurationCommand` escanea las entidades anotadas con atributos de API Platform y crea/actualiza las configuraciones automáticamente.
   - `app:config:sync-metadata` es el comando Makefile correspondiente (`make entity-setup`).
   - El `EntityConfigSynchronizer` orquesta la comparación entre los metadatos actuales de Doctrine y la configuración persistida.

3. **Exposición**:
   - Endpoint REST `GET /api/entity_configuration_dtos` sin paginación.
   - El frontend consume estos metadatos al arrancar para construir stores dinámicos, columnas de colección y campos de formulario.
   - El `EntityConfiguration` permite actualización mediante mutations GraphQL (`update`, `updateWithRelations`).

4. **Personalización**:
   - Los administradores pueden cambiar etiquetas, orden, visibilidad y tipo de input sin tocar código.
   - Los cambios se reflejan inmediatamente al recargar la configuración en el frontend.

## Consecuencias

**Positivas:**

- Separación completa entre la definición de la entidad (Doctrine) y su presentación (configuración)
- Personalización administrativa sin despliegues: cambiar etiquetas, ocultar campos, reordenar columnas
- El sistema de sincronización mantiene la configuración alineada con los cambios en las entidades
- El frontend puede construir cualquier interfaz CRUD a partir de los metadatos
- La configuración es versionable y auditable (campo `updatedAt`)

**Negativas:**

- Complejidad adicional en el backend: 4 entidades de configuración y un sincronizador
- La sincronización automática puede sobrescribir personalizaciones manuales si no se maneja cuidadosamente
- El endpoint de metadatos debe estar disponible antes de que el frontend pueda renderizar cualquier página
- El sistema actualmente no soporta configuración por rol (todos los usuarios ven los mismos metadatos)
- Caché del frontend: los metadatos se cargan al inicio; cambios requieren recarga de página
