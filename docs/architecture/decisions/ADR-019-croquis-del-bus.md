# ADR-019: Croquis del bus (asientos, chofer y puertas por planta) y mapa reutilizable

**Estado:** Aceptada

## Contexto

El mapa del bus visto desde arriba es una pieza recurrente del negocio: se usa para dar de alta y editar buses, para mostrar la ocupación de un recorrido (terminado o en curso) y, en tiempo real, para elegir asientos en la taquilla y en la venta en línea.

En el legado (TerminalOmnibus):

- Los asientos (`bus_asiento`) y las señales del mapa (`bus_senal` + `bus_senal_tipo`: chofer, puertas) pertenecen al **tipo de bus** (`bus_tipo`), compartido por todos los buses de ese tipo.
- Las coordenadas (`coordenadaX/Y`, `coordenada_x/y`) son múltiplos de 50 desde 0 en un marco de 5 columnas (4 de asientos + el pasillo, la columna 3) por N filas; `nivel2` marca la planta alta. En los buses de dos plantas los asientos clase B (reclinables) están abajo.
- El alta de un bus tiene un "lienzo": una rejilla de alto ajustable por planta y cuatro pilas infinitas (chofer, puerta, asiento A, asiento B) de las que se arrastran piezas a las celdas.

El modelo nuevo tenía `Asiento` (con `fila`/`columna` en la escala del legado, sin planta) y no tenía chofer ni puertas. La migración usaba el id de `bus_asiento` como id de `Asiento`, así que solo el **primer** bus de cada tipo recibía asientos (los demás se saltaban por "ya migrado"), y los boletos de esos otros buses apuntaban a asientos de otro bus. Además, la identidad de `asiento.id` quedaba atrás de los ids explícitos insertados.

## Decisión

**Modelo** (`App\Entity`):

- **Croquis** de un bus = sus `Asiento` + sus `BusSenal`, cada uno en una celda `(planta, fila, columna)` con coordenadas **desde 1**. La rejilla no se guarda: sus dimensiones salen de los elementos.
- `Asiento.planta` (1 = baja o única, 2 = alta).
- `BusSenal` (tabla `bus_senal`): `bus`, `tipo` (`TipoBusSenal`: `chofer`, `puerta`), `planta`, `fila`, `columna`. No es recurso de la API: se gestiona con el croquis.
- `Bus.asientos` deja de ser escribible por el CRUD genérico (`ApiProperty(writable: false)`): el único escritor es el croquis. **Cambio de contrato GraphQL:** `createBusInput`/`updateBusInput` ya no tienen `asientos` (no se usaba en el frontend); `Asiento` gana `planta`. `SCHEMA_VERSION` sube a 6 para descartar la introspección persistida.

**Reglas** (`App\Croquis\Croquis`, espejo en `frontend/src/core/croquis/model.ts`): hasta 2 plantas, filas 1–30, columnas 1–6, una celda = un elemento, número de asiento único por bus, un solo chofer. Son avisos, no errores: numeración no consecutiva, falta de chofer o de puerta, bus sin asientos (hay buses migrados así).

**API** (`BusCroquisController`, permisos `bus.read`/`bus.update` o `ROLE_ADMIN`):

- `GET /api/buses/{id}/croquis` → `{ elementos: [{ tipo, id, planta, fila, columna, numero?, clase?, conBoletos? }] }`.
- `PUT /api/buses/{id}/croquis` reemplaza el croquis entero. Los asientos se reconocen por `id`: moverlos o renumerarlos conserva el registro y sus boletos. Quitar un asiento con boletos vendidos → 422. Todo se valida antes de tocar entidades.
- `GET /api/croquis/plantillas` → croquis distintos de la flota agrupados por `Croquis::firma` con los buses que los usan (respeta `TenantFilter`).

**Migración** (`Mapeador`, `MigradorEstaticos`, `Migrador`):

- Coordenadas: `X / 50 + 1` (`Croquis::desdeLegado`); planta desde `nivel2`.
- Asientos y señales se **copian a cada bus** de su tipo, con id nuevo; ya migrado = `(bus_id, numero)` para asientos y celda para señales. Nuevo migrador de entidad `senal` ("Chofer y puertas"); los tipos de señal que no son chofer ni puerta se omiten.
- El asiento de un boleto se resuelve por `(bus del recorrido, número del bus_asiento legado)`.
- `Version20260926120000` crea `bus_senal` y `asiento.planta`, convierte las coordenadas de los buses que aún están en formato legado (alguna coordenada 0 o ≥ 50), pone en la planta alta los asientos A de los buses con asientos A y B, y reajusta la identidad de `asiento.id`.

**Frontend** (ADR-017):

- `core/croquis/`: tipos, reglas puras y transporte REST. Sin UI.
- `shared/bus-map/`: `BusMap` (carrocería por planta + rejilla), `SeatGlyph`, `SignalGlyph`, `BusMapLegend` y tokens `--bm-*`. Presentación pura, sin datos ni flujo: cada uso le pasa los elementos y, si corresponde, `estado(asiento)` (`disponible`, `ocupado`, `seleccionado`, `reservado`, `bloqueado`), `interactivo` + `@asiento`, `orientacion` (`vertical`/`horizontal`), `tamano` y el slot `celda` para pintar cada celda a su manera. **Excepción acotada a ADR-017:** `shared/` admite esta pieza de presentación del dominio porque la usan varias features (edición de buses, ocupación, venta); no puede cargar datos ni conocer ventas.
- `features/bus/`: el editor (`CroquisEditor`, estado inmutable en `editor.ts` con deshacer/rehacer, arrastre por puntero en `usePointerDrag`, pincel, teclado, rellenar 2 + 2, numeración), el diálogo de plantillas y la sección que se engancha al formulario de Bus.
- **Secciones extra del formulario genérico** (`core/entities/formExtension.ts` + `features/entity-crud/form/formExtensions.ts`): una entidad conserva el formulario dinámico de sus campos y suma secciones propias que validan antes de guardar y guardan después (`afterSave` con el registro ya creado). Si el croquis falla después de crear el bus, el formulario pasa a la edición del bus y el borrador del croquis se recupera.

Así, la venta en taquilla o en línea será: `BusMap` + `estado` derivado de la ocupación en tiempo real (Mercure) + `interactivo`; la ocupación de un recorrido terminado: `BusMap` + `estado` sin `interactivo`.

## Consecuencias

**Positivas:**

- Un solo dato (el croquis) y un solo componente de mapa para todos los usos; lo específico de cada uso (estados, selección, tooltips) vive en quien lo usa.
- Los asientos conservan su identidad al editar el croquis: los boletos históricos siguen apuntando al mismo asiento físico.
- Con la migración nueva, cada bus recibe sus asientos, chofer y puertas (antes, solo el primero de cada tipo recibía asientos).
- Plantillas agrupadas por distribución: el "tipo de bus" del legado reaparece sin volver a modelarlo.

**Negativas:**

- Guardar un bus son dos peticiones (GraphQL de los datos + REST del croquis), no una transacción. Se mitiga validando el croquis en el cliente con las mismas reglas antes de guardar y recuperando el borrador si el croquis falla tras crear el bus.
- En una base ya migrada con el esquema anterior, los buses que no eran el primero de su tipo siguen sin asientos y sus boletos apuntan a asientos de otro bus: hace falta volver a migrar asientos y señales (`asiento`, `senal`) y, para corregir los boletos, las salidas.
- La planta de los asientos ya migrados se infiere de la clase (A arriba si el bus tiene A y B); la migración nueva usa `nivel2`, que es la fuente fiel.
- `shared/bus-map` conoce vocabulario del dominio (asiento, chofer, puerta): la excepción a ADR-017 queda limitada a presentación.
- Las reglas del croquis están duplicadas en PHP y TypeScript (con tests en ambos lados).
