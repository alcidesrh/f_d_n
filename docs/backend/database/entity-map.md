# Mapa de entidades

Listado completo de entidades del dominio agrupadas por subdominio.

## Transporte

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Boleto** | `src/Entity/Boleto.php` | `boleto` | Boleto de pasaje vendido para un servicio específico |
| **Trayecto** | `src/Entity/Trayecto.php` | `trayecto` | Segmento de ruta entre dos enclaves (origen-destino) |
| **Recorrido** | `src/Entity/Recorrido.php` | `recorrido` | Combinación de trayecto + empresa + precios |
| **RecorridoMatrioska** | `src/Entity/RecorridoMatrioska.php` | `recorrido_matrioska` | Relación recursiva entre recorridos padres e hijos |
| **Servicio** | `src/Entity/Servicio.php` | `servicio` | Viaje programado: fecha, recorrido, bus, piloto |
| **Status** | `src/Entity/Status.php` | `status` | Catálogo de estados (activo/inactivo) |

## Flota

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Bus** | `src/Entity/Bus.php` | `bus` | Unidad de transporte con matrícula, marca, piloto |
| **BusMarca** | `src/Entity/BusMarca.php` | `bus_marca` | Marca/fabricante del bus |
| **Asiento** | `src/Entity/Asiento.php` | `asiento` | Asiento individual dentro de un bus, con clase (A/B) |
| **Piloto** | `src/Entity/Piloto.php` | `piloto` | Conductor con licencia, datos personales |
| **Enclave** | `src/Entity/Enclave.php` | `enclave` | Punto geográfico (estación o parada), con STI |

## Venta

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Venta** | `src/Entity/Venta.php` | `venta` | Transacción de venta que agrupa uno o más boletos |
| **Factura** | `src/Entity/Factura.php` | `factura` | Documento fiscal (DTE) emitido para una venta |
| **Cliente** | `src/Entity/Cliente.php` | `cliente` | Persona que compra boletos |

## Personal

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Usuario** | `src/Entity/Usuario.php` | `usuario` | Usuario del sistema con credenciales y roles |
| **Piloto** | `src/Entity/Piloto.php` | `piloto` | Conductor (compartido con subdominio Flota) |

## Configuración

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **EntityConfiguration** | `src/Entity/Configuration/EntityConfiguration.php` | `entity_configuration` | Metadatos de configuración por clase de entidad |
| **CollectionFieldConfig** | `src/Entity/Configuration/CollectionFieldConfig.php` | `collection_field_config` | Configuración de columnas de listado |
| **FormFieldConfig** | `src/Entity/Configuration/FormFieldConfig.php` | `form_field_config` | Configuración de campos de formulario |

## Infraestructura

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Icon** | `src/Entity/Icon.php` | `icon` | Icono del sistema (Lucide icons) |
| **IconCategory** | `src/Entity/IconCategory.php` | `icon_category` | Categoría de iconos |
| **Empresa** | `src/Entity/Empresa.php` | `empresa` | Empresa de transporte |
| **Localidad** | `src/Entity/Localidad.php` | `localidad` | Ciudad/localidad |
| **Nacion** | `src/Entity/Nacion.php` | `pais` | País (mapeado a tabla `pais`) |

## Seguridad (IAM)

| Entidad | Archivo | Tabla | Descripción |
|---------|---------|-------|-------------|
| **Usuario** | `src/Entity/Usuario.php` | `usuario` | Usuario del sistema (compartido con Personal) |
| **Role** | `src/Entity/Role.php` | `role` | Rol jerárquico del sistema |
| **Permiso** | `src/Entity/Permiso.php` | `permiso` | Grupo lógico de acciones |
| **Action** | `src/Entity/Action.php` | `action` | Acción atómica del sistema |
| **ApiToken** | `src/Entity/ApiToken.php` | `api_token` | Token de acceso bearer |

## Tablas pivote

| Tabla | Descripción |
|-------|-------------|
| `user_role` | Asignación usuario → rol |
| `role_permiso` | Asignación rol → permiso |
| `permiso_action` | Asignación permiso → acción |
| `role_action` | Acciones directas de un rol |
| `user_direct_action` | Acciones directas de un usuario |
| `user_denied_action` | Acciones denegadas de un usuario |
| `role_role` | Jerarquía de roles (padre → hijo) |
| `permiso_permiso` | Jerarquía de permisos (padre → hijo) |
| `trayecto_trayecto` | Relación trayecto → sub-trayectos |
| `icon_category_icon` | Asignación categoría → iconos |

## Clases base

| Clase | Archivo | Propósito |
|-------|---------|-----------|
| `Base` | `src/Entity/Base/Base.php` | ID autoincremental, `getLabel()` dinámico |
| `PersonaBase` | `src/Entity/Base/PersonaBase.php` | nombre, apellido, email, teléfono |
| `NombreNotaStatusBase` | `src/Entity/Base/NombreNotaStatusBase.php` | nombre, nota, status |
| `TimeLegacyStatusBase` | `src/Entity/Base/TimeLegacyStatusBase.php` | created_at, updated_at, legacy_id |
| `Precio` | `src/Entity/Embeddable/Precio.php` | Embeddable: monto + moneda (Money library) |

## Clases de entidades legacy (SQL Server)

Para el listado completo de las 112 entidades legacy, ver [Legacy SQL Server](legacy-sqlserver.md).
