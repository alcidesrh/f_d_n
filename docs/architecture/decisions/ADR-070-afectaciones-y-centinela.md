# ADR-070: Afectaciones de la logística y su centinela

**Estado:** Propuesta (usa las capacidades con alcance de [ADR-039](ADR-039-accesos-con-alcance.md), el programador de [ADR-044](ADR-044-programador-de-tareas.md), los avisos del sistema del chat de [ADR-026](ADR-026-chat-interno.md) con push de [ADR-061](ADR-061-notificaciones-app-movil-fcm.md) y el CRUD genérico)

## Contexto

Para vender boletos hacen falta estaciones con computadoras, impresoras, internet y papel, y una flota
con talleres y repuestos. Todo eso falla: a veces sin aviso (una impresora, una avería del bus), a veces
de forma previsible (el papel que se acaba, una llanta gastada). En el legado el usuario escribe por
WhatsApp al informático o al grupo de su empresa; quien lo recibe valora, pide presupuesto a la gerencia
y hace el pedido. Nada queda registrado: no hay historia para ver patrones, nadie insiste si el caso se
queda sin resolver y los contactos se van con el empleado.

Esta fase modela la **afectación** y un **centinela** que insiste hasta que se resuelve. El inventario
(existencias por estación, taller y bus) se diseñó aparte y se construye después; su detector de stock
bajo el mínimo creará afectaciones con la misma entrada que cualquier detector (`detectar`).

## Decisión

**Tipo de afectación** (`TipoAfectacion`, CRUD genérico, de lectura libre; lo escribe `afectacion.tipos`):
`codigo` (clave estable para los detectores), `nombre`, `descripcion`, `sujeto` (`estacion`, `bus` o
`empresa`: sobre qué cae), `owner` (capacidad para reportarla), `target` (capacidad para atenderla:
editarla, resolverla y recibir el primer aviso), `prioridad` (`normal` por defecto, `grave`, `urgente`),
y el ritmo del centinela: `escalarTrasMinutos` (tiempo sin resolver desde que se creó tras el que se
escala), `escalamiento` (capacidades que se suman a `target` desde entonces) e `intervaloMinutos` (cada
cuánto se repite el aviso escalado, 5 o más). `owner`, `target` y `escalamiento` se validan contra el
catálogo (`CapacidadDelCatalogo`). Un tipo inactivo no se reporta; con afectaciones no se borra.

**Afectación** (`Afectacion`, solo por `/api/afectaciones`): `tipo`, `estado` (`creada` por defecto →
`pendiente` al enviarse el primer aviso → `resuelta` cuando quien la atiende la marca), `creadaEn` y
`resueltaEn`. Para avisar a las personas correctas hace falta dónde cae: `empresa`, `estacion` y `bus`
(lo de estación pide estación; lo de bus pide el bus y toma su empresa; en lo de bus y de empresa la
estación es dónde se reportó). Además: `descripcion`, `reportadaPor` (null = la creó un detector, con
`clave` para no repetirla mientras siga abierta), `resueltaPor`, `resolucion`, y las marcas del
centinela `enviadaEn`, `escaladaEn`, `ultimoAvisoEn` y `avisos`.

**Dónde cae, por capacidad.** Cada capacidad se acota por sus propias dimensiones (ADR-039): la misma
afectación se ubica en empresa + estación para `afectacion.informatica` y solo en la empresa para
`afectacion.flota` (`ReglasAfectacion::ubicacion`). Así el informático de una estación recibe lo de su
estación y el jefe de taller lo de toda su empresa.

**Capacidades nuevas** (área Logística):

| Código | Para qué | Se acota por |
| --- | --- | --- |
| `afectacion.reportar` | Reportar lo de la estación (equipos, red, insumos, instalaciones) | empresa, estación |
| `afectacion.reportar_flota` | Reportar averías y desgaste de los buses | empresa |
| `afectacion.informatica` | Atender computadoras, impresoras, red e internet | empresa, estación |
| `afectacion.insumos` | Atender papel, tóner y consumibles | empresa, estación |
| `afectacion.instalaciones` | Atender energía, agua, local y mobiliario | empresa, estación |
| `afectacion.flota` | Atender averías, llantas y repuestos | empresa |
| `afectacion.supervisar` | Ver todas las de su alcance y recibir las que escalan | empresa |
| `afectacion.tipos` | Configurar los tipos | — |

**Centinela** (`App\Afectacion\Centinela`, reglas puras en `ReglasCentinela`). La tarea
`vigilar_afectaciones` corre cada minuto y, al reportarse una afectación, se encola una corrida para que
el primer aviso salga en segundos. Es el único que avisa y corre bajo el cerrojo de su tarea: dos
corridas no repiten un aviso. Por cada afectación abierta:

1. `creada`: aviso inicial a quien tiene `target` donde cae → `pendiente`.
2. `pendiente`: nada hasta `escalarTrasMinutos` desde que se creó.
3. Pasado ese tiempo sin resolver: escalamiento, a `target` y a las capacidades de `escalamiento`.
4. Ya escalada: el mismo aviso a los mismos cada `intervaloMinutos` desde el último, hasta `resuelta`.

Los avisos van por el canal del sistema del chat (con push al teléfono) con la afectación como tarjeta
(`TarjetaAfectacion`). Uno que falla se reintenta en la corrida siguiente. Si no hay nadie con `target`,
la afectación se queda `creada` y, al llegar el tiempo, recibe directamente el escalamiento; una ronda
sin destinatarios marca el ritmo (no se reintenta cada minuto). Al resolverse se avisa a quien la reportó.

**Quién la ve** (`ConsultaAfectaciones`): quien la reportó y quien tiene, donde cae, el `owner` (así no
se reporta dos veces), el `target`, alguna de `escalamiento` o `afectacion.supervisar`. El listado arma
esa condición en una consulta, por grupos de tipos con las mismas capacidades.

**API** (`Controller/AfectacionController`): `GET /api/afectaciones?vista=abiertas|resueltas|todas&tipo=&pagina=`,
`GET /api/afectaciones/para-reportar` (tipos que puede reportar con las empresas y estaciones donde
puede, su estación de trabajo, y las empresas, estaciones y buses para elegir),
`GET /api/afectaciones/{id}`, `POST /api/afectaciones` `{ tipo, descripcion, empresa?, estacion?, bus? }`,
`POST /api/afectaciones/{id}/editar` `{ descripcion }`, `POST /api/afectaciones/{id}/resolver` `{ resolucion? }`
y `GET /api/afectaciones/capacidades` (`afectacion.tipos`: el catálogo para elegir `owner`, `target` y
`escalamiento`). Cada fila trae `proximoAviso` y `puede: { editar, resolver }`.

**Frontend.** `/afectaciones` (`features/afectacion/`, reglas puras en `core/afectacion/`): abiertas,
resueltas o todas; «Reportar» con los tipos que puede (propone su estación de trabajo); «Corregir» y
«Resolver» en las que atiende; `?id=` la muestra arriba (el enlace de la tarjeta del aviso). Se
refresca cada minuto. Los tipos se editan en el formulario genérico, donde `owner`, `target` y
`escalamiento` se eligen del catálogo (`entity-crud/form/fieldOptions.ts`) en vez de escribirse.

**Catálogo inicial** (migración, sin pisar uno que ya exista con el mismo código), 17 tipos con
escalamiento a `afectacion.supervisar`. Urgente insiste cada 30 minutos y escala a las 2 horas; grave,
cada 4 horas y a las 24; normal, cada 24 horas y a las 72:

| Sujeto | Atiende | Tipos |
| --- | --- | --- |
| Estación | `afectacion.informatica` | Impresora averiada (urgente), Sin internet (urgente), Computadora averiada (grave), Falla de la red local (grave), Terminal de pago averiada (grave), Lector de códigos averiado (normal) |
| Estación | `afectacion.insumos` | Papel agotado (urgente), Tóner o cinta agotada (grave), Insumo agotado (normal) |
| Estación | `afectacion.instalaciones` | Sin energía eléctrica (urgente), Falla en el local (normal) |
| Bus | `afectacion.flota` | Avería del bus (urgente), Llanta dañada o desgastada (grave), Repuesto faltante (grave), Falla menor del bus (normal), GPS del bus sin señal (normal) |
| Empresa | `afectacion.informatica` | Servicio externo caído (urgente) |

## Consecuencias

- Las capacidades nuevas no están en ningún rol: hay que darlas (p. ej. `afectacion.reportar` a taquilla,
  `afectacion.informatica` al informático, `afectacion.supervisar` a la gerencia). El rol de sistema las
  tiene todas y recibe todos los avisos, como en el mantenimiento (ADR-065).
- Una afectación sin estación (un bus en ruta, un detector) solo la alcanza por estación quien tiene
  todas las estaciones; por eso los tipos de bus se atienden con `afectacion.flota`, que no mira estación.
- El primer aviso depende del worker de `tareas`; si está detenido, el tablero ya avisa que el
  programador no corre (ADR-044).
- `Afectacion` no va a la bitácora campo a campo (ADR-051): el centinela la toca en cada ronda y sus
  propias fechas ya cuentan su historia.
- Queda fuera: el inventario y sus detectores, presupuestos y pedidos a proveedores, adjuntar fotos,
  reabrir una resuelta, y la pantalla de configuración propia de los tipos (por ahora, el CRUD genérico).
