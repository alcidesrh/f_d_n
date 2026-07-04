# Módulo Transporte

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Trayecto` | `automation` | Rutas o trayectos de buses |
| `Salida` | `departure_board` | Salidas programadas |
| `Boleto` | `transit_ticket` | Boletos emitidos |
| `Venta` | — | Ventas de pasajes |
| `Recorrido` | — | Recorridos completos |
| `Ruta` | `route` | Rutas de transporte |
| `Viaje` | `departure_board` | Viajes realizados |
| `Cliente` | `people` | Clientes/ pasajeros |
| `Tarifa` | `sell` | Tarifas y precios |
| `Horario` | `schedule` | Horarios programados |

## Páginas

- **Rutas dinámicas**: `/lista/Trayecto`, `/form/Trayecto/:id?`, etc.
- **BoletoVentaPage**: Ruta `/venta/boleto` (ver módulo Venta)
- **Ruta boletos**: `/boletos` y `/boletos/emitir` (ver `boleto.ts`)

## Componentes

Los componentes de transporte se manejan principalmente a través del CRUD dinámico. Componentes específicos:
- `BoletoVentaPage.vue` — Página de venta de boletos (en `pages/venta/`)
- `SeatIcon.vue` — Icono de asiento para selección

## Rutas

Definidas en `src/router/boleto.ts`:
```
/boletos              → UserCollection.vue
/boletos/emitir       → BoletoVentaPage.vue
```

Además, las entidades de transporte usan las rutas dinámicas:
```
/lista/Boleto         → DynamicCollection.vue
/form/Boleto/:id?     → DynamicForm.vue
```

## Almacenes

Usa stores dinámicas creadas por `storeFactory`. No tiene stores específicas.
