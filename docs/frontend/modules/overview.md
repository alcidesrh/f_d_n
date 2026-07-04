# Módulos del Frontend

La aplicación se organiza en módulos funcionales que agrupan páginas, componentes, stores y rutas relacionadas. Cada módulo se corresponde aproximadamente con un subdominio del backend.

## Mapeo módulos ↔ subdominios

| Módulo | Subdominio Backend | Propósito |
|--------|-------------------|-----------|
| **Transporte** | Transporte | Rutas, trayectos, salidas, boletos, venta de pasajes |
| **Flota** | Flota | Buses, marcas, asientos, mantenimiento |
| **Venta** | Venta | Venta de boletos, boletoVentaPage, SeatIcon |
| **Personal** | Personal | Pilotos, conductores, empleados |
| **Configuración** | Configuración | Admin de entidades, configuración visual, editor de campos |
| **Infraestructura** | Infraestructura | Estaciones, paradas, terminales, localidades |
| **Seguridad** | IAM | Usuarios, roles, permisos, acciones |
| **Dashboard** | — | Panel principal, estadísticas, cards |

## Estructura de un módulo

No existe un directorio `src/modules/` estructurado formalmente. En su lugar, los módulos se definen por la ubicación de sus archivos:

```
src/
├── pages/{modulo}/       # Páginas específicas del módulo
├── components/{modulo}/  # Componentes del módulo
├── router/{modulo}.ts    # Rutas del módulo
├── stores/{modulo}/      # Stores específicas (si aplica)
└── i18n/{locale}/{modulo}.ts  # Traducciones (si aplica)
```

## Rutas dinámicas vs estáticas

La mayoría de los módulos usan **rutas dinámicas** (`/lista/:entity`, `/form/:entity/:id?`) que renderizan los componentes genéricos `DynamicCollection` y `DynamicForm`. Solo los módulos que necesitan comportamiento específico tienen rutas estáticas:

- **Seguridad**: rutas manuales para User, Role, Permiso con componentes específicos
- **Venta**: ruta `/venta/boleto` para `BoletoVentaPage.vue`
- **Configuración**: ruta `/admin/entities/:action/:entity` para `EntityConfig.vue`

## Entidades por módulo

Las entidades editables se definen en `schemaStore.editables`:

```
Action, Role, Permiso, Status, ApiToken, Usuario, Localidad, Nacion,
Asiento, Bus, Empresa, Piloto, BusMarca, Boleto, Salida, Trayecto,
Enclave, Cliente, Estacion, Parada, Venta, Recorrido, Icon, IconCategory
```

## Stores de módulo

Además de las stores dinámicas, existen stores específicas en `src/stores/`:
- `stores/user/` — Store de usuario
- `stores/role/` — Store de rol
- `stores/permiso/` — Store de permiso
- `stores/action/` — Store de acción
- `stores/localidad/` — Store de localidad
