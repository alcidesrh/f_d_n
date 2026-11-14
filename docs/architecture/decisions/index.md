# Registro de Decisiones Arquitectónicas (ADR)

> Todas las decisiones arquitectónicas significativas para el proyecto FDN Transportes.

| ADR | Título | Estado |
|-----|--------|--------|
| [ADR-001](ADR-001-api-platform-graphql.md) | API Platform + GraphQL como capa primaria de datos | Aceptada |
| [ADR-002](ADR-002-dual-entity-manager.md) | Dual Entity Manager (PostgreSQL + SQL Server legacy) | Reemplazada por ADR-056 |
| [ADR-003](ADR-003-flat-permission-set.md) | Flat Permission Set vs RBAC tradicional | Reemplazada por ADR-039 |
| [ADR-004](ADR-004-dynamic-crud-store-factory.md) | Dynamic CRUD via runtime store factory | Aceptada |
| [ADR-005](ADR-005-frankenphp-mercure.md) | FrankenPHP + Caddy + Mercure integrado | Aceptada |
| [ADR-006](ADR-006-subdomains-bounded-contexts.md) | Subdominios como bounded contexts | Aceptada |
| [ADR-007](ADR-007-formkit-custom-theme.md) | FormKit con tema personalizado + JSON schemas | Aceptada |
| [ADR-008](ADR-008-legacy-migration-strategy.md) | Estrategia de migración desde TerminalOmnibus | Cumplida (código quitado: ADR-056) |
| [ADR-009](ADR-009-autoimport-system.md) | Sistema de auto-import mediante unplugin | Reemplazada por ADR-017 |
| [ADR-010](ADR-010-dynamic-entity-config.md) | Configuración dinámica de entidades vía metadatos | Aceptada (enmendada por ADR-036) |
| [ADR-011](ADR-011-recorrido-lleva-trayecto.md) | Recorrido lleva el trayecto directamente (sin plantilla intermedia) | Aceptada |
| [ADR-012](ADR-012-trayecto-canonico-por-enclaves.md) | Trayecto canónico por par de enclaves (origen, destino) | Aceptada (en parte reemplazada por ADR-030) |
| [ADR-013](ADR-013-rename-servicio-a-recorrido.md) | Renombrar la entidad Servicio a Recorrido | Aceptada |
| [ADR-014](ADR-014-estados-tipados-por-entidad.md) | Estados tipados por entidad (enum) en vez de catálogo Status genérico | Aceptada (para `BoletoAsiento`, en parte reemplazada por ADR-037; `Status` eliminado: ADR-056) |
| [ADR-015](ADR-015-tenant-filter.md) | TenantFilter: aislamiento por empresa vía Doctrine SQLFilter | Aceptada (filtro por las empresas de las asignaciones: ADR-039) |
| [ADR-016](ADR-016-piloto-vuelve-a-bus.md) | La asignación de Piloto vuelve a Bus (no Recorrido); pilotoAux → copiloto | Aceptada |
| [ADR-017](ADR-017-frontend-por-capas.md) | Frontend por capas (core / shared / features) con imports explícitos | Aceptada |
| [ADR-018](ADR-018-taxonomia-agnostica-y-menus.md) | Taxonomía agnóstica (referencia polimórfica) y menús de navegación sobre ella | Aceptada (visibilidad sin jerarquía de roles: ADR-039) |
| [ADR-019](ADR-019-croquis-del-bus.md) | Croquis del bus (asientos, chofer y puertas por planta) y mapa reutilizable | Aceptada |
| [ADR-020](ADR-020-frontend-mobile-first.md) | Frontend mobile-first con breakpoints centralizados | Aceptada |
| [ADR-021](ADR-021-venta-de-asientos.md) | Venta de asientos por tres canales (taquilla, agencia, web) con factura electrónica | Aceptada (enmendada por ADR-030; configuración de Forcon y Cybersource por ADR-047) |
| [ADR-022](ADR-022-rename-recorrido-a-salida.md) | Renombrar la entidad Recorrido a Salida (código y base de datos) | Aceptada |
| [ADR-023](ADR-023-pagina-web-compra-en-una-pagina.md) | Página web: compra en una sola página, ida y vuelta, recargo, idiomas y SEO | Aceptada |
| [ADR-024](ADR-024-gestion-de-salidas.md) | Gestión de salidas: programador, esquemas guardados y editar/anular/eliminar con propagación | Aceptada |
| [ADR-025](ADR-025-seguimiento-de-buses-en-recorrido.md) | Seguimiento de buses en recorrido: mapa en tiempo real con GPS simulado | Aceptada (el mapa, reemplazado por ADR-055) |
| [ADR-026](ADR-026-chat-interno.md) | Chat interno: conversaciones, registros compartidos como tarjetas vivas y avisos privados por Mercure | Aceptada |
| [ADR-027](ADR-027-croquis-molde-y-bus-inferido.md) | Croquis como molde para elegir el bus y bus inferido en la migración | Aceptada |
| [ADR-028](ADR-028-anulacion-reasignacion-y-bitacora.md) | Anulación y reasignación de boletos (con anulación de la factura en Forcon) y bitácora de boletos y salidas | Aceptada |
| [ADR-029](ADR-029-listado-sobre-css-grid.md) | Listado genérico sobre CSS Grid: columnas, filtros OR/AND, selección, edición en línea y tarjetas | Aceptada |
| [ADR-030](ADR-030-ruta-con-paradas-ordenadas.md) | Ruta con paradas ordenadas; el trayecto queda como par comercial | Aceptada |
| [ADR-031](ADR-031-bus-por-hora-y-cambio-de-bus-por-numero.md) | Bus por hora en el programador (distribución y bus por fila) y cambio de bus con boletos por número de asiento | Aceptada |
| [ADR-032](ADR-032-abordaje-y-chequeo-de-boletos.md) | Abordaje de salidas y chequeo de boletos; estado cancelado (no se presentó) | Aceptada |
| [ADR-033](ADR-033-listado-de-boletos.md) | Listado de boletos (filtros y acciones del legado) y cancelar desde el listado | Aceptada |
| [ADR-034](ADR-034-depositos-de-agencia.md) | Depósitos de agencia (registrar, acreditar o rechazar) y consulta de saldos | Aceptada |
| [ADR-035](ADR-035-encomiendas.md) | Encomiendas: envío con artículos, cuatro modalidades, tarifas del legado, embarque con transbordo y bitácora | Aceptada (transiciones de estado reemplazadas por ADR-038) |
| [ADR-036](ADR-036-metadata-del-cliente-por-revisiones.md) | Metadata del cliente por revisiones: el navegador se pone al día solo (automático) o cuando un administrador publica (manual) | Aceptada |
| [ADR-037](ADR-037-flujo-del-boleto-con-symfony-workflow.md) | Flujo del boleto con `symfony/workflow`: transiciones con nombre, guards y acciones calculadas por el backend | Aceptada |
| [ADR-038](ADR-038-flujo-de-la-encomienda-con-symfony-workflow.md) | Flujo de la encomienda con `symfony/workflow`: transiciones con nombre, guards y acciones del listado calculadas por el backend | Aceptada |
| [ADR-039](ADR-039-accesos-con-alcance.md) | Accesos con alcance: capacidades en el código, roles planos y asignaciones por empresa y estación; denegar por defecto en la API genérica | Aceptada |
| [ADR-040](ADR-040-autorizaciones-pases-de-un-solo-uso.md) | Autorizaciones: pases de un solo uso para lo que una regla impide (sin permiso, límite diario, fuera de hora; fuera de estado, ADR-058) | Aceptada |
| [ADR-041](ADR-041-datos-del-pago-en-la-pagina-web.md) | Datos del pago en la página web: dirección de la tarjeta por listas (país → región → ciudad) y factura a otro nombre o a la razón social del NIT | Aceptada |
| [ADR-042](ADR-042-sincronizacion-temporal-con-el-legado.md) | Sincronización temporal con el legado: el asiento se escribe primero en el otro sistema, triggers y ciclo por minuto para traer, apartado en FDN para lo que vende el legado, interruptor con indicador | Aceptada |
| [ADR-043](ADR-043-configuracion-del-listado-por-rol.md) | Configuración del listado por rol: varias por entidad, la por defecto sin roles, un rol en una sola, botones Seleccionar/Nuevo/Editar/Eliminar configurables | Aceptada |
| [ADR-044](ADR-044-programador-de-tareas.md) | Programador de tareas a la medida: catálogo cerrado sobre `symfony/scheduler`, colas `tareas` y `pesadas`, latido y alerta en el tablero, umbrales diarios, reportes por suscripción y esquemas de salidas automáticos | Aceptada |
| [ADR-045](ADR-045-mensajes-predeterminados-del-chat.md) | Mensajes predeterminados del chat: instancias con audiencia y contenido (con horario, salida iniciada, anomalías de reasignación y tarifa, ventas y desempeño del día) a los avisos del sistema; bitácora de tarifas | Aceptada |
| [ADR-046](ADR-046-latencia-de-la-api.md) | Latencia de la API: firewall sin sesión, cargas por lotes (paradas con su enclave, tarifas, accesos de la administración), tablero precalculado por el programador, metadata de API Platform en memoria y caché de estáticos | Aceptada |
| [ADR-047](ADR-047-configuracion-de-forcon-y-cybersource-en-la-base.md) | Configuración de Forcon y Cybersource en la base: una fila por proveedor, leída en cada uso, poblada por la migración desde el entorno y `services.yaml`, editable con `app:fel:configuracion` / `app:pago:configuracion` | Aceptada |
| [ADR-048](ADR-048-correo-de-la-empresa.md) | Correo de la empresa: una cuenta `usuario@sistemafdn.com` por usuario, copia por buzón, borradores que se guardan solos, configuración en la base (tokens de MailPace por dominio, remitentes, webhook) editable en la administración, entrada por el webhook firmado y el correo como sección del chat | Aceptada |
| [ADR-049](ADR-049-navegacion-por-capacidad.md) | Navegación por capacidad: lo que exige cada pantalla se sincroniza desde el router, el backend poda menús y administración, la audiencia por roles es opcional (sin roles = a todos) y desaparece el rol base; enmienda 2026-11: el listado de lo que se lee libre solo se muestra a quien puede editarlo | Aceptada |
| [ADR-050](ADR-050-historial-gps-y-reproduccion-de-trayectorias.md) | Historial GPS y reproducción de trayectorias: lecturas por bus (clave bus + instante, una cada 2 min con el bus quieto), la salida se reconoce por su horario, endpoints por día y por salida, purga a los 90 días y pantalla `/seguimiento/reproducir` | Aceptada |
| [ADR-051](ADR-051-bitacora-diaria-de-usuarios.md) | Bitácora diaria de los usuarios: `actividad` particionada por mes y escrita en lote al terminar cada petición (sesiones, peticiones, sondeos agregados y pantallas de la SPA), `request_id` que une la petición con la bitácora de negocio, cambios campo a campo de todas las entidades, resumen diario, retención configurable (2 meses) y traza de todos o solo de los seleccionados, todo configurable en la administración | Aceptada |
| [ADR-052](ADR-052-consolidacion-de-roles-del-legado.md) | Consolidación de los roles del legado en puestos: lo que el usuario podía hacer se cubre con la menor cantidad de puestos sin dar nada que escriba, `ROLE_ENCARGADO_TAQUILLA` para la estación, la cortesía como puesto aparte (reemplaza la negación `ROLE_NO_VENDER_VOUCHER`), roles idénticos o de solo lectura unidos, y `app:acceso:consolidar-roles` (simula sin `--aplicar`) para la base ya migrada | Propuesta |
| [ADR-053](ADR-053-espacio-util-y-contexto.md) | Espacio útil y contexto en la presentación: panel derecho cerrado en laptops, título en la cabecera móvil, barra del listado con menú «Más», reglas de rejilla, piso de legibilidad y guardia e2e de desbordes | Aceptada |
| [ADR-054](ADR-054-suplantacion-de-usuarios.md) | Suplantación de usuarios: el administrador del sistema entra como otro usuario con un token aparte, corto y marcado, nunca como otro administrador del sistema, sin tocar credenciales ni accesos, cada petición en la bitácora con quién la hizo y la configuración (duración, chat, aviso, motivo) en la administración | Aceptada |
| [ADR-055](ADR-055-mapas-propios.md) | Mapas propios: MapLibre GL en `shared/mapa`, mapa base de Protomaps como un archivo PMTiles servido por Caddy en `/mapas/`, rutas por carretera con un Valhalla propio (perfil bus) en lugar de las teselas de OpenStreetMap y el OSRM públicos, y `make mapas` para preparar los datos | Aceptada |
| [ADR-056](ADR-056-refactorizacion-arquitectura-rendimiento-y-limpieza.md) | Refactorización general: el motor de importación por salida pasa a la sincronización y la conexión al legado se abre al usarla, un solo entity manager (fuera `EntitySistemaFdn` y `Status`), índices para la sincronización, las ventas por fecha, la bitácora y la búsqueda de clientes (`nat_*` solo en migraciones), arranque del frontend en capas (`base`, `nucleo`, entrada) y fuera la migración masiva con su pantalla y su capacidad | Aceptada |
| [ADR-057](ADR-057-rol-de-sistema-opera-boletos-y-encomiendas-en-cualquier-estado.md) | El rol de sistema anula, reasigna y cancela boletos en cualquier estado salvo anulado o reasignado, sin mirar la salida ni la hora, y anula encomiendas en cualquier estado: la máquina admite esos estados de origen y el guard los cierra a los demás con el rechazo de siempre | Aceptada |
| [ADR-058](ADR-058-autorizacion-fuera-de-estado.md) | Autorización fuera de estado: una vez, lo que el rol de sistema hace siempre (anular y reasignar boletos en cualquier estado con valor sin mirar la salida, anular encomiendas que ya viajaron); la aprueba quien aprueba autorizaciones y además puede hacer esa operación ahí | Aceptada |
| [ADR-059](ADR-059-modificacion-de-compras-por-el-cliente.md) | «Mis compras»: el cliente entra sin usuario ni contraseña con una llave firmada ligada a su correo (queda en el navegador al comprar, va en el correo del boleto o se pide por correo), ve sus viajes (próximos y pasados con su factura) y modifica los próximos (hora, ruta, asiento, clase, agregar asientos): paga la diferencia con tarjeta, no hay reembolso si es menor, penalización y límites configurables, y la factura se anula y se emite otra si cambia lo que va al certificador | Aceptada |
