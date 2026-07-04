# Componentes de Formulario CRUD

## DynamicForm.vue

**Archivo**: `src/components/crud/form/DynamicForm.vue`

Componente principal de formulario dinámico. Renderiza un schema FormKit generado desde la configuración de entidad.

**Props**: Ninguna (usa `getStore()` para obtener la store del parámetro de ruta).

**Slots**:
- `CrudButton`: reemplaza los botones CRUD por defecto

**Comportamiento**:
- Obtiene la store vía `getStore()` en `onBeforeMount`
- Renderiza `<FormKitSchema :schema="store.formSchema">`
- El schema incluye un slot `#crudBtn` para los botones
- Maneja submit: crea o actualiza según `store.item.id`
- Post-submit exitoso: redirige a lista o resetea formulario
- Muestra `FormPreload` mientras el schema no está listo

**Métodos**:
- `submit(data)`: ejecuta `store.submit()`, muestra notificación, redirige
- `cancel()`: limpia item y redirige a lista

**Librería interna**: registra `FormKitMessages` como componente disponible en el schema.

## CrudButton.vue

**Archivo**: `src/components/crud/form/CrudButton.vue`

Botones de acción del formulario.

**Props**:
- `edit`: boolean que indica si es modo edición (true) o creación (false)

**Eventos**:
- `submit`: guardar/crear entidad
- `delete`: eliminar entidad (solo en edición)
- `cancel`: cancelar operación

**Comportamiento**:
- Modo creación: botón "Guardar"
- Modo edición: botones "Guardar", "Eliminar", "Cancelar"
- Usa Quasar Dialog para confirmación de eliminación
