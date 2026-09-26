# ADR-020: Frontend mobile-first con breakpoints centralizados

**Estado:** Aceptada

## Contexto

El frontend estaba hecho solo para escritorio. Por debajo de 1024px no se podía usar:

- Los sidebars (`position: fixed`) quedaban siempre visibles y tapaban el contenido. Además,
  GSAP escribía en línea `margin-left`/`margin-right` de 250px sobre `.main`, así que en un
  teléfono el área de contenido tenía ancho negativo.
- Las reglas `@media (max-width: 1024px)` de `main.css` apuntaban a clases (`.closed`,
  `.mobile-hidden`) que ningún componente aplicaba, y el resto del bloque responsive
  (`.grid-kpi`, `.form-grid`, `.route-bar-row`…) era de maquetas que ya no existen.
- Había breakpoints repartidos: 1024px en JS (`ui.ts`, sin uso), 1024/720/520px en CSS, 800px
  en una container query y los de Tailwind en los templates.
- La cabecera usaba anchos fijos (marca de 230px, bloque izquierdo de 250px), los formularios
  tenían 4rem de padding fijo y algunos diálogos un `width` fijo.

## Decisión

1. **Mobile-first** con cuatro escenarios: base (mobile), `md` 48rem (tablet), `lg` 64rem
   (desktop) y `xl` 80rem (wide). Son los valores de Tailwind, declarados en
   `assets/tokens.css` y reflejados en `app/breakpoints.ts`.
2. **Shell:** desde `lg` se mantiene el comportamiento anterior (sidebars fijos
   `open`/`mini`/`close` que empujan el contenido). Por debajo, cada sidebar es un drawer
   sobre el contenido con fondo, Escape y cierre al navegar. Es el mismo componente con los
   mismos ítems: no hay una navegación móvil aparte.
3. **GSAP anima variables CSS y CSS decide el layout.** `sidebarStore.sidebarUpdate()` solo
   escribe `--sb-<lado>-w`/`--sb-<lado>-open`, y `sidebar.css`/`content.css` las aplican
   según el breakpoint. El texto de los ítems en `mini` también se oculta por CSS.
   `--sb-<lado>-w` está registrada con `@property` sin herencia y se escribe solo sobre
   `.sidebar` y `.main` (GSAP anima un número en JS y la escribe en `onUpdate`, sin leer
   estilos). Animada en `:root`, recalculaba el estilo de toda la página en cada fotograma
   y la transición avanzaba a tirones.
4. **JS solo para comportamiento.** `useUiStore().isMobile` sigue el cruce de `lg` con
   `matchMedia` (no con `resize`) y decide si el botón de menú abre el drawer o alterna el
   modo. El estado del drawer no se persiste.
5. **Container queries** para los componentes que dependen del ancho que les queda
   (formulario genérico, constructor de menús, croquis), con `.main` como contenedor.
6. **Patrones compartidos en el nivel global:** los overlays de PrimeVue se limitan al
   viewport, las tablas no `scrollable` scrollean por dentro, el start/end de los toolbars
   hacen wrap, y la utilidad `tap-target` da área táctil con puntero grueso.

Las reglas están en [docs/frontend/responsive.md](../../frontend/responsive.md).

## Consecuencias

**Positivas:**

- Ninguna página desborda en horizontal entre 320 y 1600px. En escritorio (≥ 1024px, y ≥ 800px
  de contenido) el aspecto no cambia.
- Hay una sola escala de breakpoints y un solo lugar donde se define cada regla del shell.

**Negativas / a tener en cuenta:**

- En la cabecera de móvil, si los menús de `topbar_right` no caben, su franja scrollea en
  horizontal. Pasa con más de ~4 acciones en 320px.
- Por debajo de `lg` no se ve el historial de navegación de la cabecera. Es secundario: el
  título está en la página y el navegador tiene "atrás".
- `--page-gutter` conserva el umbral de 800px de `.main`, que es previo a este ADR, para no
  cambiar el espaciado de escritorio.
