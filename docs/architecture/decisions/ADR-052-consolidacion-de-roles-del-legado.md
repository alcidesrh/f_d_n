# ADR-052: Consolidación de los roles del legado en puestos

**Estado:** Propuesta (modifica la migración de accesos de ADR-039)

## Contexto

ADR-039 migró cada rol del legado (`custom_rol`, 34 más `ROLE_USER`) a un rol del modelo nuevo con las capacidades de `EquivalenciasLegado`, y cada rol de cada usuario a una asignación. Así se copió también la manera del legado de dar permisos: roles chicos que se apilan (vender, chequear en la web, chequear en el móvil, reimprimir, reasignar…) y una negación (`ROLE_NO_VENDER_VOUCHER`). El modelo nuevo no funciona así: un rol es un puesto y no hay negaciones.

Las asignaciones reales de la base de desarrollo (exportadas el 2026-11-05: 1.179 asignaciones de 158 usuarios, 35 roles) lo muestran:

- **Se apilan siempre juntos.** 60 de los 131 usuarios activos que no son administradores tienen exactamente lo mismo en su estación: la unión de `ROLE_SUPERVISOR_BOLETO` y `ROLE_SUPERVISOR_ENCOMIENDA`, armada con 6 a 10 roles cada uno. Entre los usuarios con `ROLE_SUPERVISOR_BOLETO`, el 97 % tiene también `ROLE_VENDEDOR_BOLETOS`, el 94 % `ROLE_CHECK_BOLETO_WEB` y el 92 % `ROLE_SUPERVISOR_ENCOMIENDA`. En total hay solo 16 perfiles de estación distintos.
- **La mayoría de las asignaciones no aporta nada.** Las capacidades que dan ya las da otro rol del usuario en el mismo alcance: 81 de 87 `ROLE_VENDEDOR_BOLETOS`, 80 de 88 `ROLE_CHECK_BOLETO_WEB`, 79 de 82 `ROLE_RECEPTOR_ENCOMIENDAS`, 45 de 46 `ROLE_CHECK_BOLETO_MOVIL`, 42 de 43 `ROLE_ADMIN_REIMPRIMIR_FACTURA`, 33 de 34 `ROLE_SUPERVISOR_BOLETO_VOUCHER` y 8 de 8 `ROLE_EMISOR_CORTESIA`. Los administradores (`ROLE_ADMIN`) tienen además los 30 roles restantes.
- **Hay roles idénticos.** `ROLE_CHECK_BOLETO_MOVIL` = `ROLE_CHECK_BOLETO_WEB`, y `ROLE_ENTREGA_MULTIPLE_ENCOMIENDA` = `ROLE_RECEPTOR_ENCOMIENDAS`.
- **La negación se perdió.** `ROLE_NO_VENDER_VOUCHER` no concede nada en el modelo nuevo, pero 24 usuarios que lo tenían emiten hoy cortesías por `ROLE_SUPERVISOR_BOLETO`, `ROLE_ADMIN_ESTACION` o `ROLE_AUTORIZADOR`, algo que el legado les impedía.

## Decisión

**Los roles del legado se consolidan en puestos** (`App\Acceso\Legado\ConsolidacionRoles`, puro):

1. Lo que el usuario podía hacer en el legado se calcula por tipo de alcance (estación u oficina): las capacidades de sus roles (`EquivalenciasLegado::ROLES`) menos lo que le negaban (`NIEGA`: `ROLE_NO_VENDER_VOUCHER` quita `venta.cortesia`).
2. Eso se cubre con la menor cantidad de puestos (`PUESTOS`) de ese alcance que no den nada fuera de ello. En la estación no se repite lo que ya da la asignación de oficina sobre las mismas empresas. Con un rol total (sistema o `ROLE_ADMIN`) basta ese.
3. Lo que no se pueda cubrir exacto se cubre con el puesto que **solo agregue lectura** (`.ver`, reportes), cubra más y sea más chico. Nunca se amplía con algo que escribe: si hiciera falta, queda en `sinCubrir` para otorgarlo a mano. En los datos actuales no pasa.

**El catálogo de puestos:**

- Nuevo: `ROLE_ENCARGADO_TAQUILLA`, la unión de supervisor de boletos y de encomiendas, para el perfil más común de las estaciones.
- **La cortesía es un puesto aparte** (`ROLE_EMISOR_CORTESIA`, de estación o de oficina): `ROLE_SUPERVISOR_BOLETO`, `ROLE_ADMIN_ESTACION`, `ROLE_AUTORIZADOR` y `ROLE_ADMIN_EMPRESA` ya no la traen. Así «no vende vouchers» es no otorgar ese puesto, como decía la ayuda de accesos. Sin `venta.vender` la cortesía no se ejerce (`RegistroVenta`), así que no se migra sola.
- Se unen: `ROLE_CHECK_BOLETO_WEB` y `ROLE_CHECK_BOLETO_MOVIL` → `ROLE_CHEQUEO_BOLETO` (renombrado, conserva el id); `ROLE_ENTREGA_MULTIPLE_ENCOMIENDA` → `ROLE_RECEPTOR_ENCOMIENDAS`; `ROLE_INSPECTOR_BOLETO`, `ROLE_INSPECTOR_ENCOMIENDA` y `ROLE_MANIFIESTO_ESPECIAL` → `ROLE_REVISOR` (solo lectura); `ROLE_SUPERVISOR_AGENCIA` → `ROLE_ADMINISTRATIVOS`. `ROLE_NO_VENDER_VOUCHER` desaparece.
- Siguen los demás. `ROLE_ADMIN_REIMPRIMIR_FACTURA` y `ROLE_SUPERVISOR_BOLETO_VOUCHER` quedan como puestos chicos de consulta para quien solo tenga eso, aunque hoy nadie los necesita.

**Dónde se aplica:**

- **Migración** (`RolesUsuarioLegado`, pasos «usuario» e «iam», `app:acceso:alcance-legado`): crea solo los puestos y asigna los consolidados. Los roles del legado que no son puestos ya no se crean.
- **Base ya migrada**: `app:acceso:consolidar-roles` hace la misma consolidación sin el legado. Parte de lo que da hoy cada rol en la base, así que respeta lo editado. Además deja los puestos con sus capacidades, renombra, copia las audiencias (menús, administración, listados) del rol retirado a su reemplazo, y las de los roles que absorbe el encargado de taquilla también a él. Borra los roles retirados que ya nadie tiene. A quien tenía a mano un puesto que pierde la cortesía se le otorga la emisión de cortesías con el mismo alcance, vigencia y otorgante. Todo va en una transacción. **Sin `--aplicar` solo informa.** Es idempotente y respeta lo otorgado a mano.

## Consecuencias

En la base de desarrollo (simulado con el comando sobre una copia):

- Las asignaciones migradas bajan de 1.176 a 399, y de 35 roles quedan 29 (24 con asignaciones; sin asignaciones quedan `ROLE_GERENTE`, `ROLE_ADMIN_TIEMPOS` y `ROLE_SUPERVISOR_PORTAL`, que solo tenían administradores, y los dos de consulta).
- Ningún usuario pierde nada, salvo la cortesía de los 24 que el legado no dejaba emitirla. 20 usuarios ganan solo lectura: `encomienda.ver` (14), `boleto.ver` (5), `salida.ver` (2) y un reporte (1), por la unión en `ROLE_REVISOR` y en `ROLE_ADMINISTRATIVOS`.
- Configurar un puesto de estación es una asignación, no ocho.

**Pendiente / a revisar:**

- Es una decisión de negocio (como `EquivalenciasLegado`): validar el encargado de taquilla y la cortesía aparte con quien opera cada puesto.
- Los límites por rol no se mueven solos: si un puesto que pierde la cortesía tenía `cortesias_por_dia`, hay que ponerlo en `ROLE_EMISOR_CORTESIA`. Hoy ninguno tiene límites.
- 85 asignaciones migradas de puestos de estación tienen `estaciones = []` (el usuario no tenía estación en el legado) y no conceden nada. La consolidación las conserva así. Siguen en «Sin alcance».
- El comando no escribe en la bitácora de accesos, igual que la migración.
- Las fixtures de desarrollo (`RoleFixtures`) siguen con el supervisor de boletos que emite cortesías.
