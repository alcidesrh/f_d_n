# Responsive (mobile-first)

Reglas del frontend para todas las pantallas. La decisión y su contexto están en
[ADR-020](../architecture/decisions/ADR-020-frontend-mobile-first.md).

> **Una pantalla no está terminada si solo funciona en escritorio.** Hay que revisarla en
> móvil (320–430px), tablet (768px), escritorio (1024–1440px) y pantalla ancha (1600px+).

## Breakpoints

Son los de Tailwind y se declaran en `src/assets/tokens.css` (`@theme`). En JS están en
`src/app/breakpoints.ts`, con los mismos valores (hay un test que verifica que coincidan).

| Nombre | Desde | Escenario | Qué cambia en el shell |
|---|---|---|---|
| (base) | 0 | Mobile | Sidebars como drawers, cabecera compacta |
| `md` | 48rem (768px) | Tablet | Nombre de usuario y texto de los menús de la cabecera, marca de agua "FDN", pantalla completa |
| `lg` | 64rem (1024px) | Desktop | Sidebars fijos (`open`/`mini`/`close`) que empujan el contenido, historial de navegación en la cabecera |
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
| `--page-gutter` | Margen interior de página y de superficies grandes. Va de 1rem a 2rem según el ancho de `.main`, y pasa a 4rem cuando `.main` mide ≥ 800px |
| `--content-max` (120rem) | Ancho máximo del contenido en pantallas muy anchas |
| `--tap-min` (2.75rem) | Área táctil mínima |
| `--header-h`, `--sb-<lado>-w`, `--sb-<lado>-open` | Shell. `sidebarStore` escribe los anchos |

La tipografía se mantiene: raíz de 14px; `.page-title` 1.2rem en todos los tamaños; el resto
según Tailwind y PrimeVue. Achicar la letra no es una forma de hacer que algo quepa.

## Layout

- **Shell** (`app/layout/`): la cabecera es fija. Desde `lg`, `.main` se corre
  `--sb-left-w`/`--sb-right-w`. Por debajo, los sidebars son drawers (`sidebarStore.drawer`,
  no se persiste) que se abren con los botones de menú de la cabecera y se cierran con el
  fondo, con Escape, al navegar o al pasar a escritorio. Solo puede haber uno abierto. Los
  ítems de navegación son los mismos en ambos casos (una sola fuente: `NavArea`).
- **Scroll:** un solo nivel por página, en `.main` (el `body` no scrollea) o en `BlankLayout`.
  Los sidebars y los overlays scrollean por dentro. No anides scrolls sin una razón.
- **Página:** `.main-inner` aplica `--page-gutter` y `--content-max`. No pongas márgenes
  laterales fijos en las páginas.

## Componentes

- **Cabecera de página**: usá `<Toolbar>` con `#start` → `<PageHead>` y `#end` → acciones. El
  start/end del toolbar hacen wrap (`primevue/toolbar.css`). Poné `flex-wrap` en el contenedor
  de las acciones para que bajen de línea en móvil.
- **Formularios:** una columna en la base. Para pasar a más columnas, usá una container query
  sobre el formulario (`@container` + `@xl:grid-cols-2`), como en `EntityForm`/`formSchema`.
  Los labels siempre visibles y los inputs a todo el ancho (`<Fluid>`).
- **Tablas:** si no entran, scroll horizontal **dentro** de la tabla (en `scrollable` es
  automático; en las demás lo resuelve `primevue/datatable.css`). Las acciones de fila van en
  una columna `frozen` a la derecha, así siempre se ven. Columnas: ancho mínimo fluido (ver
  `.col-head`). El paginador tiene un template reducido debajo de `md` (`ListFooter`).
- **Cards/grids:** ancho completo en móvil y `grid-cols-1` → `md:`/`xl:` (o
  `grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]`). Evitá los anchos y altos fijos.
- **Dialogs/overlays:** con `width: 44rem` alcanza. `primevue/overlay.css` los limita al
  viewport, y PrimeVue limita la altura con scroll interno. Para anchos relativos, usá
  `min(64rem, 96vw)`.
- **Controles táctiles:** los botones de ícono del shell usan `.icon-btn` (38px). Los íconos
  sueltos que son botones (acciones de fila, controles de columna) llevan la utilidad
  `tap-target`, que con puntero táctil agranda el área a `--tap-min` sin cambiar nada con
  mouse. Todo control clicable es un `<button>` con `aria-label` si no tiene texto.
- **Contenido ocultable:** en móvil solo se oculta lo decorativo o lo redundante (ver la
  tabla de breakpoints). Lo importante nunca se oculta: se reacomoda o scrollea.

## Checklist de una pantalla nueva

1. Maquetala primero a 360px, sin breakpoints.
2. Sumá `md:`/`lg:`/`xl:` o container queries solo donde el espacio permita un layout mejor.
3. Sin desborde horizontal de página en 320px. Los anchos fijos van con `min()`/`max-width`.
4. Revisá los estados: vacío, cargando, error, textos largos y muchas columnas.
5. Reutilizá los patrones de arriba. Si falta uno, agregalo a este documento antes de crear
   una excepción.
