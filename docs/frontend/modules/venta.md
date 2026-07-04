# Módulo Venta

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Venta` | — | Ventas realizadas |
| `Boleto` | `transit_ticket` | Boletos de pasaje |
| `Cliente` | `people` | Clientes |

## Páginas

### BoletoVentaPage (`src/pages/venta/BoletoVentaPage.vue`)

Página principal de emisión de boletos. Ruta: `/venta/boleto` (nombre `venta_boleto`). Es una de las pocas páginas con componente específico, fuera del CRUD dinámico.

**Meta**: `{ breadcrumb: 'Venta de Boletos', icon: 'sym_o_airplane_ticket' }`

**Funcionalidad**: Interfaz de venta de pasajes que permite seleccionar origen, destino, fecha, horario y asientos.

### SeatIcon (`src/pages/venta/SeatIcon.vue`)

Componente de visualización de asientos para selección en la venta de boletos. Renderiza una cuadrícula de asientos con estados (disponible, ocupado, seleccionado).

## Rutas

Definidas en `src/router/boleto.ts` y `src/router/routes.ts`:

```
/venta/boleto        → BoletoVentaPage.vue    (ruta directa en routes.ts)
/boletos             → UserCollection.vue     (ruta en boleto.ts)
/boletos/emitir      → BoletoVentaPage.vue    (ruta en boleto.ts)
```

Además, rutas dinámicas:
```
/lista/Boleto        → DynamicCollection.vue
/form/Boleto/:id?    → DynamicForm.vue
/lista/Venta         → DynamicCollection.vue
/form/Venta/:id?     → DynamicForm.vue
/lista/Cliente       → DynamicCollection.vue
/form/Cliente/:id?   → DynamicForm.vue
```

## Flujo de venta

1. Usuario navega a `/venta/boleto`
2. Selecciona origen, destino y fecha
3. El sistema muestra horarios disponibles
4. Usuario selecciona asientos (componente `SeatIcon`)
5. Confirma la venta
6. Se emite el boleto vía GraphQL mutation

## Almacenes

Usa stores dinámicas para `Venta`, `Boleto` y `Cliente`. No tiene stores específicas de venta.
