# ADR-069: Distribución de la venta en la portada: dona con la cola agrupada

**Estado:** Propuesta

## Contexto

La primera gráfica de la portada reparte lo vendido de un día (hoy por defecto) o un rango, de todas
las empresas del alcance o de las que se marquen, en cuatro vistas: por canal, por estación (la página y las agencias
como dos estaciones más), por usuario y, en boletos, por tipo. Con datos reales el reparto tiene una
cola larga: en octubre vendieron 38 estaciones, las 3 primeras hacen 55% y 30 quedan bajo 2% (la menor,
0.03%: 0.2 px de arco en una dona de 240 px); un día venden unos 65 usuarios. Una cuña así no se lee ni
se acierta con el dedo (WCAG 2.5.8 pide 24 px).

Se consideraron dos ideas: una lupa como la del detalle de producto de una tienda en línea, y repartir
los datos en varias gráficas ordenadas de mayor a menor.

## Decisión

### Datos (`GET /api/tablero/distribucion?desde=&hasta=&empresas=`)

- Permiso `reporte.ventas`, con el alcance de empresas y estaciones (ADR-039) en la consulta; sin
  `empresas` (ids separados por coma; en pantalla, casillas con «Todas» por defecto), todas las del
  alcance. Un día o un rango de hasta 366 días (`FiltroDistribucion`), por
  fecha de venta. Se calcula en la petición (una consulta agregada; el rango es libre y no hay copia).
- Lo vendido es lo que el tablero cuenta como ingreso: boletos cobrados que no se anularon ni se
  reasignaron, los no presentados incluidos.
- Cada boleto en un solo tipo (`DistribucionVentas::tipo()`): anulados y reasignados por su estado;
  de los demás, cortesías y vouchers por no cobrarse (las ventas de agencia siempre se cobran); los
  cobrados que no abordaron son cancelados; los pospuestos de la página siguen cobrados.

### Gráfica (`DonaPorciones`, reglas puras en `core/tablero/porciones.ts`)

- Dona de mayor a menor desde las 12 en el sentido del reloj, con el total en el centro, junto a una
  lista con todo el nivel. Canales y tipos conservan su orden fijo (se comparan de un día a otro sin
  que las cuñas cambien de lugar).
- **Cola agrupada:** hasta 6 cuñas propias, cada una de al menos 5% del nivel (18°: aloja un blanco
  táctil de 24 px en un teléfono); el resto va a "Otros" (gris), que nunca agrupa una sola. Sin ángulo
  mínimo artificial: inflar las cuñas chicas deja de representar los valores.
- **Abrir "Otros"** (cuña o fila) vuelve a repartir la cola en la misma dona con las mismas reglas, con
  su camino de vuelta; el centro y la lista siguen en porcentaje del total, no del nivel.
- **La lista** trae también lo que agrupa "Otros", en una sola escala: el porcentaje es del total y la
  barra, contra la mayor. Puntero, dedo o foco sobre una fila o una cuña las resaltan juntas y el
  centro dice su valor; una fila de la cola resalta "Otros".
- **Táctil y teclado:** la cuña se busca por ángulo y cada una se toca en al menos 8°; con el dedo la
  elección queda al levantarlo. La dona es una sola parada de Tab: flechas recorren las cuñas, Enter
  abre "Otros", Escape vuelve. Tabla completa para lectores de pantalla.
- Sin texto en las cuñas: los nombres van en la lista (la estación con su nombre completo y su sigla). Colores de la paleta categórica
  validada para daltonismo, en orden fijo; los de los tipos, elegidos para que cada par vecino pase.

## Alternativas descartadas

- **La lupa.** Aumentar no cambia el ángulo ni la proporción de la cuña: la de 0.03% necesitaría unas
  100× para llegar a 24 px, y la lente tapa el círculo que da sentido a la parte. La versión ojo de pez
  deforma justo lo que se lee (Cockburn et al. 2009). En el teléfono no hay "pasar por encima" y el dedo
  tapa lo que toca. Ninguna librería de gráficas la trae. Lo que se conserva de la idea es ver qué hay
  bajo el puntero, como datos (centro y fila), no como píxeles.
- **Varias gráficas a la vez.** Cada una redibuja su parte como 100%, con una escala distinta que no se
  ve, y el segundo pastel de 30 estaciones vuelve a ser de astillas; Tufte, Datawrapper y SAP lo
  desaconsejan. Se conserva separar la cabeza de la cola: "Otros" se abre como otro nivel de la misma
  gráfica, de uno en uno y con el porcentaje del total a la vista.

## Consecuencias

- "Otros" puede ser la cuña mayor (por usuario, unos 70% en un mes); la línea de lectura sobre la dona
  ("los 3 primeros suman 42%; 26 de 39 quedan bajo 2%") y la lista cuentan esa concentración.
- El color de una estación o un usuario es el de su puesto en el nivel: puede cambiar de un día a otro.
- Los umbrales (6 cuñas, 5%) son convenciones de las guías y de la geometría, no de un estudio; están
  en `REGLAS` para ajustarlos con uso real.
