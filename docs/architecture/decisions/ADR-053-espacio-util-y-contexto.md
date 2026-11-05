# ADR-053: Espacio útil y contexto en la presentación

**Estado:** Aceptada (complementa ADR-020)

## Contexto

ADR-020 dejó el frontend usable por debajo de 1024px. Una auditoría de todas las pantallas en seis
anchos (320 a 1920px) mostró lo que quedaba pendiente:

- **Espacio.** Los dos sidebars abiertos se llevaban 500px: a 1440px el contenido medía 827px para
  mostrar cuatro títulos plegados por panel, y el tablero caía a una columna.
- **Contexto.** Debajo de `lg` nada decía en qué pantalla se estaba: las migas no caben y casi
  ninguna página lleva título. Los dos paneles compartían ícono, y Ayuda y Apariencia no tenían
  acceso en el teléfono.
- **Desbordes con una misma causa.** La barra de los listados llegaba a 445px en un teléfono de 390;
  rejillas sin columna base crecían con su contenido; los layouts de página medían el viewport y no
  lo que deja `.main`; `.campo`, copiado en 14 archivos, anulaba con su `min-width: 0` las `min-w-*`
  que evitan que un selector quede en «El…».
- **Lectura.** `text-xs` eran 11,25px y había unos 70 textos informativos entre 7,5 y 10,8px. Las
  barras de desplazamiento estaban ocultas en toda la app: una tabla de 15 columnas no decía que
  seguía. Los títulos pedían una fuente (Space Grotesk) que nunca se cargó.
- **Tacto.** Campos de 15px, por debajo de los 16px con los que Safari acerca la página al enfocarlos,
  y controles de PrimeVue bajo el área táctil mínima.

## Decisión

1. **El panel derecho de menús arranca cerrado por debajo de 96rem** (`sidebarStore`). Es la
   navegación secundaria: el botón de la cabecera lo abre y el modo se recuerda. Los paneles de una
   ruta (`meta.panel`) siguen abiertos: son los controles de esa pantalla. Estrena clave de
   persistencia (`panel-derecho`): lo guardado no distingue una elección de un valor por defecto.
2. **La cabecera dice dónde se está.** Debajo de `lg` muestra el título de la pantalla y mide 3.5rem;
   desde `lg`, 4rem con las migas, y el título pasa a la página (`PageHead`). Nunca los dos a la vez.
3. **Lo que opera, a la vista; lo que configura, en un menú.** La barra del listado deja filtrar,
   seleccionar y crear; vista, columnas y restablecer pasan a «Más» cuando no cabe. Las acciones por
   fila son la principal más un menú con nombres, no una hilera de íconos sin texto.
4. **Un solo scroll.** Las tarjetas de `DataGrid` siguen a la página; solo la tabla tiene alto propio,
   por su cabecera fija.
5. **Reglas de rejilla** (`responsive.md`): columna base explícita, `minmax(0,1fr)` y container
   queries en lo que vive dentro de `.main`. Un campo suelto usa la utilidad `campo`.
6. **Piso de legibilidad:** `text-xs` a 12px y `0.75rem` como mínimo en lo informativo; fuera quedan
   los contadores en círculo y las etiquetas del mapa, y el login y la 404, que tienen composición
   propia. Una sola familia en la interfaz: Roboto Flex.
7. **Con puntero fino las barras de desplazamiento se ven**, finas; `scrollbar-gutter: stable` evita
   que el contenido salte entre páginas. Con el dedo quedan las del sistema.
8. **Táctil en un solo lugar:** `primevue/touch.css` da el área mínima y los campos de 16px, solo con
   puntero grueso.
9. **Guardia:** `e2e/responsive.spec.ts` recorre las pantallas en cuatro anchos y falla si alguna
   desborda, nombrando qué elementos se salen.

## Consecuencias

**Positivas:**

- Ninguna pantalla de la guardia desborda de 320 a 1280px, y dejar de cumplirlo rompe un test.
- A 1440px el contenido mide más de 1100px: las tablas muestran casi todas sus columnas y el tablero
  vuelve a dos columnas.
- Una sola fuente para reglas que estaban copiadas: `campo`, `.mapa-chip`, `primevue/toolbar.css`.

**Negativas / a tener en cuenta:**

- Quien usaba el panel derecho abierto en una laptop lo encuentra cerrado una vez. Abrirlo es un clic y
  se recuerda. La clave anterior (`right`) queda sin uso en `localStorage`.
- Las acciones de una fila de Salidas están a un clic más, a cambio de nombres en lugar de íconos. El
  menú solo ofrece lo posible en ese estado: ya no explica por qué falta una acción.
- `scrollbar-gutter: stable` reserva unos píxeles a la derecha de `.main` aunque la página no
  desplace.
