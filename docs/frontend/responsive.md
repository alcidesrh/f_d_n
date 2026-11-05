# Responsive (mobile-first)

Reglas del frontend para todas las pantallas. Las decisiones y su contexto están en
[ADR-020](../architecture/decisions/ADR-020-frontend-mobile-first.md) y
[ADR-053](../architecture/decisions/ADR-053-espacio-util-y-contexto.md).

> **Una pantalla no está terminada si solo funciona en escritorio.** Hay que revisarla en
> móvil (320–430px), tablet (768px), escritorio (1024–1440px) y pantalla ancha (1600px+). El
> spec `e2e/responsive.spec.ts` recorre las pantallas en cuatro anchos y falla si alguna desborda.

## Breakpoints

Son los de Tailwind y se declaran en `src/assets/tokens.css` (`@theme`). En JS están en
`src/app/breakpoints.ts`, con los mismos valores (hay un test que verifica que coincidan).

| Nombre | Desde | Escenario | Qué cambia en el shell |
|---|---|---|---|
| (base) | 0 | Mobile | Sidebars como drawers, cabecera de 3.5rem con el título de la pantalla |
| `md` | 48rem (768px) | Tablet | Nombre de usuario y texto de los menús de la cabecera, reloj y marca de agua "FDN", pantalla completa |
| `lg` | 64rem (1024px) | Desktop | Sidebars fijos (`open`/`mini`/`close`) que empujan el contenido, cabecera de 4rem con el historial de navegación; el título pasa a la página |
| `xl` | 80rem (1280px) | Wide | Sin cambios en el shell; se usa para layouts de página |

- **Mobile-first:** el estilo base es el de móvil y los breakpoints suman con `min-width`
  (`md:`, `lg:`, `@variant lg {}`, `@media (width >= 64rem)`). No uses `max-width`.
- No crees breakpoints nuevos ni cortes por dispositivo. `sm`/`2xl` existen en Tailwind, pero
  no son cortes del layout.
- En CSS global (`src/assets/*.css`) usá `@variant md|lg|xl { … }`. En `<style>` de un SFC
  preferí clases en el template; si hace falta una media query, escribí el valor en rem
  (`@media (width >= 48rem)`) y no en px.
- **Container queries** (`@container`, clases `@md:`, `@xl:`…) cuando el componente depende
  del ancho que **le queda** y no del viewport. `.main` es un contenedor (`main`) que ya
  descuenta los sidebars. Ejemplos: las columnas del formulario genérico, `MenuBuilderPage`,
  `CroquisEditor`.
- **JS** solo si el cambio es de comportamiento: `useUiStore().isMobile` (debajo de `lg`)
  decide si el botón de menú abre un drawer o cambia el modo del sidebar. Nada visual depende
  de JS.

## Tokens

| Token | Uso |
|---|---|
| Escala de Tailwind (`p-2`, `gap-4`…, `--spacing` = 0.25rem) | Espaciado. No uses px sueltos (`13px`, `17px`) |
| `--page-gutter` | Margen lateral de página y de superficies grandes. Continuo según el ancho de `.main`: 1rem hasta 500px y 2.5rem desde 1250px, sin saltos |
| `--page-top`, `--page-bottom` | Margen arriba y abajo de la página. Debajo de `md` el de abajo deja libre el disparador del chat. Una pantalla de alto fijo los resta (`ChatPage`) |
| `--tap-min` (2.75rem) | Área táctil mínima |
| `--header-h`, `--sb-<lado>-w`, `--sb-<lado>-open` | Shell. `sidebarStore` escribe los anchos |

Tipografía: raíz de 15px y Roboto Flex en toda la app; `.page-title` 1.2rem; `text-xs` son 12px
(`tokens.css`) y lo informativo no baja de `0.75rem` (solo los contadores en círculo y las
etiquetas del mapa). Achicar la letra no es una forma de hacer que algo quepa.

## Layout

- **Shell** (`app/layout/`): la cabecera es fija. Desde `lg`, `.main` se corre
  `--sb-left-w`/`--sb-right-w`. Por debajo, los sidebars son drawers (`sidebarStore.drawer`,
  no se persiste) que se abren con los botones de la cabecera y se cierran con el
  fondo, con Escape, al navegar o al pasar a escritorio. Solo puede haber uno abierto. Los
  ítems de navegación son los mismos en ambos casos (una sola fuente: `NavArea`). El panel
  derecho de menús arranca cerrado por debajo de 96rem; el de una ruta (`meta.panel`), abierto.
- **Scroll:** un solo nivel por página, en `.main` (el `body` no scrollea) o en `BlankLayout`.
  Los sidebars y los overlays scrollean por dentro. No anides scrolls sin una razón: las
  tarjetas de `DataGrid` siguen a la página y solo la tabla tiene alto propio (cabecera fija).
  Con puntero fino las barras se ven, finas: una tabla ancha debe decir que sigue.
- **Página:** `.main-inner` aplica los márgenes. No pongas márgenes laterales fijos en las
  páginas.
- **Rejillas:** siempre con columna base (`grid grid-cols-1 @4xl:grid-cols-2`) y `minmax(0,1fr)`
  en lugar de `1fr` suelto: una columna implícita o `1fr` crecen con su contenido y empujan la
  página. Lo que vive en `.main` mide su ancho con `@…:`, no con `lg:` (con sidebars no es lo
  mismo); los diálogos, fuera de `.main`, usan `sm:`/`md:`.

## Componentes

- **Cabecera de página**: usá `<Toolbar>` con `#start` → `<PageHead>` y `#end` → acciones, sin
  clases: su aspecto sale de `primevue/toolbar.css`. Debajo de `lg` el título no se repite (lo
  dice la cabecera) y un `<Toolbar>` sin acciones no se dibuja. Poné `flex-wrap` en el
  contenedor de las acciones para que bajen de línea en móvil.
- **Formularios:** una columna en la base. Para pasar a más columnas, usá una container query
  sobre el formulario (`@container` + `@xl:grid-cols-2`), como en `EntityForm`/`formSchema`.
  Los labels siempre visibles y los inputs a todo el ancho (`<Fluid>`). Un campo suelto lleva la
  utilidad `campo` (rótulo sobre el control); en una fila con `flex-wrap`, una `min-w-*` al lado
  hace que baje de línea antes de aplastar el control.
- **Tablas:** si no entran, scroll horizontal **dentro** de la tabla (en `scrollable` es
  automático; en las demás lo resuelve `primevue/datatable.css`). Las acciones de fila van en
  una columna `frozen` a la derecha, así siempre se ven. Columnas: ancho mínimo fluido (ver
  `.col-head`). El paginador tiene un template reducido debajo de `md` (`ListFooter`).
- **Barras de un listado:** lo que opera sobre los datos siempre a la vista; lo que configura la
  vista, en un menú «Más» cuando no cabe (`ListToolbar`, por el ancho de la barra).
- **Acciones por fila:** la principal a la vista y el resto en un menú con sus nombres
  (`SalidasPage`). Más de tres íconos sin texto por fila no se leen ni caben en un teléfono.
- **Cards/grids:** ancho completo en móvil y `grid-cols-1` → `md:`/`xl:` (o
  `grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]`). Evitá los anchos y altos fijos.
- **Dialogs/overlays:** con `width: 44rem` alcanza. `primevue/overlay.css` los limita al
  viewport, y PrimeVue limita la altura con scroll interno. Para anchos relativos, usá
  `min(64rem, 96vw)`.
- **Controles táctiles:** con puntero táctil los controles de PrimeVue tienen el área mínima
  `--tap-min` y los campos 16px, por debajo de eso Safari acerca la página al enfocarlos
  (`primevue/touch.css`). Los botones de ícono del shell usan `.icon-btn`. Los íconos sueltos que
  son botones (acciones de fila, controles de columna) llevan la utilidad `tap-target`, que hace
  lo mismo sin cambiar nada con mouse. Todo control clicable es un `<button>` con `aria-label` si
  no tiene texto.
- **Contenido ocultable:** en móvil solo se oculta lo decorativo o lo redundante (ver la
  tabla de breakpoints). Lo importante nunca se oculta: se reacomoda o scrollea.

## Checklist de una pantalla nueva

1. Maquetala primero a 360px, sin breakpoints.
2. Sumá `md:`/`lg:`/`xl:` o container queries solo donde el espacio permita un layout mejor.
3. Sin desborde horizontal de página en 320px. Los anchos fijos van con `min()`/`max-width`.
   Sumá la ruta a `e2e/responsive.spec.ts` (`npm run test:e2e -- responsive`).
4. Revisá los estados: vacío, cargando, error, textos largos y muchas columnas.
5. Reutilizá los patrones de arriba. Si falta uno, agregalo a este documento antes de crear
   una excepción.
