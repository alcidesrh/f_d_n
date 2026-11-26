# ADR-071: Inventario de la logística, fase 1

**Estado:** Propuesta (usa las capacidades con alcance de [ADR-039](ADR-039-accesos-con-alcance.md), las afectaciones y su centinela de [ADR-070](ADR-070-afectaciones-y-centinela.md) y el CRUD genérico)

## Contexto

Las afectaciones (ADR-070) dicen qué falla y a quién avisar, pero no cuánto papel queda en una estación,
qué repuestos tiene un taller ni a dónde fue lo que se compró. En el legado eso vive en hojas sueltas o
en la memoria de cada encargado. La empresa no tiene bodega central: cada estación, taller, oficina y
bus guarda lo suyo, y lo que no se consigue en el lugar se manda desde otro almacén, casi siempre de la
capital. El diseño completo (activos con serie, proveedores, presupuestos y compras) está en el
documento «Gestión logística: inventario y afectaciones»; esta fase es la base: qué hay, dónde, a qué
costo y por qué cambió, con la alerta de bajo mínimo.

## Decisión

**Artículo** (`Articulo`, CRUD genérico, de lectura libre; lo escribe `inventario.articulos`): `codigo` y
`nombre` únicos, `unidad` (rollo, caja, litro, unidad), `categoria`, `serializado` (se seguirá uno por
uno con los activos; por ahora se cuenta como los demás), `tipoAfectacion` (el tipo que crea su faltante;
si no dice, el del almacén), `descripcion` y `activo`. El catálogo es de todas las empresas.

**Almacén** (`Almacen`, CRUD genérico, de lectura libre; lo escribe `inventario.articulos`): `nombre`
(único en su empresa), `tipo` (`estacion`, `taller`, `oficina` o `bus`), `empresa` (obligatoria: en una
estación compartida cada empresa tiene el suyo), `estacion` (obligatoria en el de estación) y `bus` (solo
y siempre en el de bus, de la misma empresa), `activo`. Donde está decide quién lo ve y quién lo mueve.

**Existencia** (`Existencia`, solo por `/api/inventario`): una por almacén y artículo, con `cantidad`
(2 decimales), `costoPromedio` (promedio ponderado de lo que ha entrado, 4 decimales) y los niveles
`minimo`, `puntoReorden` (en el mínimo o encima) y `maximo` (encima de los dos). Estado:
**agotado** (nada), **bajo mínimo**, **por reponer** (en el punto de reorden o debajo) o **bien**.
Cuánto reponer: hasta el máximo, o sin máximo hasta el mayor de los niveles, menos lo que viene en camino.

**Movimiento** (`Movimiento`, solo por `/api/inventario`): el libro de cada almacén. Cada línea lleva el
artículo, la cantidad con signo, el costo unitario, el `saldo` que dejó, el documento (factura, guía),
el motivo, quién y cuándo, y la afectación que lo motivó si la hay. No se edita: un error se corrige con
otro movimiento. Tipos:

| Tipo | Qué es | Costo | Capacidad |
| --- | --- | --- | --- |
| `entrada` | Lo que llega comprado | El de la factura; rehace el promedio | `inventario.mover` |
| `salida` | Lo que se gasta o se instala | El promedio | `inventario.mover` |
| `envio` | Sale hacia otro almacén de la misma empresa; queda «en camino» | El promedio | `inventario.mover` en el origen |
| `recepcion` | Llegó el envío: entra completo al destino | El del envío | `inventario.mover` en el destino |
| `ajuste` | Un conteo: deja lo contado y anota la diferencia, con motivo | El promedio | `inventario.ajustar` |

Cantidades y costos se calculan en enteros (centésimas y diezmilésimas, `App\Inventario\Cantidad`), sin
flotantes. `App\Inventario\Inventario` aplica cada cambio y su movimiento en la misma transacción, con la
fila de la existencia bloqueada (`INSERT … ON CONFLICT DO NOTHING` y `SELECT … FOR UPDATE`): dos salidas
a la vez no dejan un negativo. Una salida o un envío de más se rechaza (409 `existencia_insuficiente`).
Un almacén inactivo no recibe ni envía, pero se cuenta y recibe lo que ya venía en camino; un artículo
inactivo ya no entra ni se envía. Un envío que llega de menos o roto se recibe completo y se corrige con
una salida o un ajuste.

**Detector del faltante** (`AlertasExistencia`). Tras el commit de cada cambio (un movimiento o los
niveles), si la existencia quedó debajo de un mínimo puesto, crea la afectación con
`Afectaciones::detectar` (clave `existencia:{almacén}:{artículo}`, una sola mientras siga abierta) del
tipo del artículo o, si no dice, `insumo_agotado` en estaciones y oficinas y `repuesto_faltante` en
talleres y buses. Al volver al mínimo (o quitarlo) la resuelve como el sistema
(`Afectaciones::resolverDetectada`). Si falla, el movimiento ya quedó; el siguiente cambio lo reintenta.

**Capacidades nuevas** (área Logística):

| Código | Para qué | Se acota por |
| --- | --- | --- |
| `inventario.ver` | Ver las existencias y los movimientos | empresa, estación |
| `inventario.mover` | Entradas, salidas, envíos y recepciones | empresa, estación |
| `inventario.ajustar` | Contar y ajustar | empresa, estación |
| `inventario.articulos` | Mantener artículos, almacenes y niveles | empresa |

Un almacén se ve con cualquiera de las cuatro donde está. Uno sin estación (un taller aparte, un bus)
solo lo alcanza por estación quien tiene todas las estaciones de su empresa, como en ADR-070.

**API** (`Controller/InventarioController`, `/api/inventario`): `GET /almacenes` (los que ve, con
`faltantes` y `puede: { mover, ajustar, niveles }`), `GET /almacenes/{id}/existencias`,
`GET /almacenes/{id}/movimientos?articulo=&pagina=`, `GET /almacenes/{id}/destinos`,
`POST /almacenes/{almacen}/articulos/{articulo}/niveles` `{ minimo, puntoReorden?, maximo? }`,
`GET /articulos`, `GET /en-camino`, `GET /por-reponer` (lo que toca reponer en los almacenes que ve,
lo más grave primero), `POST /movimientos`
`{ tipo, almacen, articulo, cantidad, costo?, destino?, documento?, motivo?, afectacion? }` y
`POST /envios/{id}/recibir` `{ documento? }`. Errores: `{ error, codigo }`.

**Frontend.** `/inventario` (`features/inventario/`, reglas puras en `core/inventario/`): el almacén, sus
existencias con su estado, «Registrar» (entrada, salida, envío o conteo), los niveles de cada artículo,
lo que va en camino con «Recibir», lo que toca reponer en todos los almacenes que ve y el libro.

## Consecuencias

- Las capacidades nuevas no están en ningún rol: hay que darlas (p. ej. `inventario.mover` al encargado
  de cada estación y taller, `inventario.articulos` a logística). El rol de sistema las tiene todas.
- Ni una existencia ni el libro se borran con su almacén o su artículo (las llaves no tienen
  `ON DELETE`): se desactivan.
- `Movimiento` no va a la bitácora (ADR-051): es su propio registro. `Existencia` sí, por sus niveles.
- La cantidad de una existencia es la suma de su libro; si algo la desajusta, un conteo la corrige y
  deja la diferencia anotada.
- Queda fuera: activos con número de serie y su historia; proveedores, solicitudes de compra y
  presupuestos con aprobación (toda rotura pide presupuesto aprobado por la gerencia, cualquiera el
  monto; una vez aprobado, se compra en el lugar o se envía desde la capital); conteos cíclicos e
  indicadores. El documento de diseño tenía `inventario.trasladar`: en esta fase lo cubre
  `inventario.mover`.
