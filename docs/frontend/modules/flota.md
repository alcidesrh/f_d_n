# Módulo Flota

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Bus` | `directions_bus` | Unidades de transporte |
| `BusMarca` | `sell` | Marcas / fabricantes de buses |
| `Asiento` | `airline_seat_recline_extra` | Configuración de asientos |
| `Status` | `badge` | Estados de bus (operativo, taller, etc.) |

## Páginas

Todas las entidades usan rutas dinámicas CRUD:
```
/lista/Bus         → DynamicCollection.vue
/form/Bus/:id?     → DynamicForm.vue
/lista/BusMarca    → DynamicCollection.vue
/form/BusMarca/:id? → DynamicForm.vue
/lista/Asiento     → DynamicCollection.vue
/form/Asiento/:id? → DynamicForm.vue
/lista/Status      → DynamicCollection.vue (endpoint: statuses)
/form/Status/:id?  → DynamicForm.vue
```

## Detalles de implementación

### Entity Status
La entidad `Status` tiene un tratamiento especial en `storeFactory.ts`:
```typescript
collectionEndpoint(store) {
  if (store.name == 'Status') return 'statuses'
  return `${str.decapitalize(store.name)}s`
}
```

### Asientos
La entidad `Asiento` se relaciona con `Bus` y forma parte de la configuración de la flota. En los formularios, los asientos se muestran como select con opciones cargadas dinámicamente.

## Componentes

No tiene componentes específicos — usa los componentes genéricos del CRUD dinámico. El `SeatIcon.vue` (en `pages/venta/`) se usa para visualizar asientos en la venta de boletos.

## Almacenes

Usa stores dinámicas. No tiene stores específicas en `src/stores/`.
