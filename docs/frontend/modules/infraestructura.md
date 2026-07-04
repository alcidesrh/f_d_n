# Módulo Infraestructura

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Estacion` | `subway` | Estaciones de transporte |
| `Parada` | `back_hand` | Paradas de ruta |
| `Enclave` | `location_on` | Enclaves / puntos geográficos |
| `Localidad` | `location_city` | Localidades / ciudades |
| `Nacion` | `public` | Países / naciones |
| `Terminal` | `location_city` | Terminales de transporte |

## Páginas

Todas las entidades usan rutas dinámicas CRUD:
```
/lista/Estacion      → DynamicCollection.vue
/form/Estacion/:id?  → DynamicForm.vue
/lista/Parada        → DynamicCollection.vue
/form/Parada/:id?    → DynamicForm.vue
/lista/Enclave       → DynamicCollection.vue
/form/Enclave/:id?   → DynamicForm.vue
/lista/Localidad     → DynamicCollection.vue
/form/Localidad/:id? → DynamicForm.vue
/lista/Nacion        → DynamicCollection.vue
/form/Nacion/:id?    → DynamicForm.vue
```

## Relaciones

- `Localidad` pertenece a `Nacion`
- `Estacion` se ubica en una `Localidad`
- `Parada` pertenece a un `Recorrido` o `Trayecto`
- `Enclave` es un punto geográfico genérico

## Almacenes

- `src/stores/localidad/`: Store específica para Localidad (cache de opciones)
- Stores dinámicas para las demás entidades

## Notas

- `Nacion` y `Localidad` suelen cargarse como opciones para selects en formularios de otras entidades
- `Estacion` tiene relación con `Localidad` y se usa en la emisión de boletos como origen/destino
