# Apariencia: CSS en tres niveles y parámetros del usuario

Cómo está organizado el CSS del frontend y cómo se cambia la apariencia (Mi cuenta → Apariencia).
La decisión y sus alternativas están en [ADR-072](../architecture/decisions/ADR-072-apariencia-parametrizable.md).

## Dónde está cada cosa

```
frontend/src/styles/
  main.css              única entrada (la importa main.ts): el orden de los @import es el de los niveles
  tema/
    apariencia.css      nivel 1: parámetros --apariencia-* con su valor de fábrica
    paleta.css          nivel 2: las rampas de Tailwind leen de las de PrimeVue (se invierten en oscuro)
    tokens.css          nivel 2: breakpoints, radios, fondos, estados, sombras, movimiento, layout
    fuentes.css         Roboto Flex
  base.css              nivel 3: html, body, enlaces, foco, scrollbars, transición `fade`
  shell/                nivel 3: cabecera, sidebars y área de contenido
  primevue/             nivel 3: lo de PrimeVue que sus tokens no alcanzan
  utilidades.css        nivel 3: utilidades propias (`campo`, `tap-target`, `superficie`…)

frontend/src/app/apariencia/
  ajustes.ts            catálogo: cada ajuste, sus opciones, el de fábrica y su parámetro CSS (puro, con tests)
  store.ts              useAparienciaStore: lo elegido, aplicarlo a la página y guardarlo
  preset.ts             el preset de PrimeVue (estilo + colores + componentes) conectado a los parámetros
  componentes.ts        ajustes por componente del preset
  tokens.ts             ayudas puras para tokens de PrimeVue (invertir rampas, `contrastado`, referencias)
  EditorApariencia.vue  la sección de Mi cuenta, generada desde el catálogo
```

El CSS que solo sirve a un componente queda en su `<style scoped>`: aquí va solo lo global.

## Los tres niveles

Son los mismos de PrimeVue (primitivos → semánticos → componentes), un nivel más arriba:

1. **Parámetros** (`--apariencia-*`): los rasgos generales del diseño que el usuario puede cambiar. Son números
   o valores CSS cortos. El valor de fábrica está en `tema/apariencia.css`; el elegido lo escribe la store en
   el `style` de `<html>` (solo si no es el de fábrica), y gana.

   | Parámetro | Fábrica | Qué mueve |
   |---|---|---|
   | `--apariencia-texto` | `15px` | `font-size` de `<html>`: todo lo que mide en `rem` (textos, controles, espaciado de Tailwind) |
   | `--apariencia-fuente` | Roboto Flex | `--font-sans` de Tailwind y el body |
   | `--apariencia-esquinas` | `1` | escala de radios de Tailwind (`rounded-*`), la de PrimeVue y sus radios chicos |
   | `--apariencia-densidad` | `1` | rellenos de PrimeVue (`padding*`), menús del sidebar, filas del `DataGrid` |
   | `--apariencia-sombras` | `1` | opacidad de las sombras de PrimeVue, `--shadow`, `--shadow-sm`, cabecera y sidebars |
   | `--apariencia-movimiento` | `1` | `transitionDuration` de PrimeVue, `--transition*`, las animaciones GSAP de los sidebars |
   | `--apariencia-contraste` | `0` | textos y bordes de PrimeVue y `--text-body` (0 normal, 1 alto) |
   | `--apariencia-fondo` | tenue | `--bg-page`: fondo de la página y de la cabecera |

2. **Tokens**: los de PrimeVue (`--p-*`), las rampas de Tailwind que leen de ellos y los de la app
   (`--bg-page`, `--bg-panel`, `--text-body`, `--c-success|warning|danger|info`, `--shadow`, `--transition`,
   `--header-h`…). Los tokens se escriben con los parámetros (`calc(0.3s * var(--apariencia-movimiento))`).
   En el preset de PrimeVue lo hace `conectarParametros` (`preset.ts`) al generarlo: multiplica los radios,
   rellenos, opacidades de sombra y duraciones literales del estilo base. Las referencias (`{border.radius.md}`)
   ya siguen a su origen.
3. **Consumidores**: el CSS que pinta. Usa tokens, nunca parámetros directamente, salvo en un `calc()` para
   algo propio que deba seguirlos (la densidad del `DataGrid`, el relleno de un menú).

Con los valores de fábrica todo mide y se ve igual que antes de los parámetros: cada `calc()` multiplica por 1
y cada `color-mix()` del contraste mezcla al 0%.

## Qué sigue a la apariencia y qué no

Sigue sola cualquier cosa escrita con tokens: `rounded-lg`, `var(--radius-md)`, `var(--p-border-radius-md)`,
`var(--transition)`, `var(--shadow)`, las rampas (`text-surface-600`, `var(--p-primary-500)`), los componentes
de PrimeVue y lo que mide en `rem`.

No la siguen, a propósito o por ahora:

- Literales: `border-radius: 0.6rem`, `transition: .2s`, `box-shadow: 0 2px 4px #0002`. Úsalos solo para lo que
  debe quedar fijo.
- `rounded-full`, `50%` y los radios de PrimeVue de más de 12px (pastillas, interruptores, chips): son formas.
- Las sombras de Tailwind (`shadow-sm`, `shadow-lg`) y las animaciones propias de PrimeVue (las de los overlays).
- Los píxeles: lo que mide en `px` no crece con «Tamaño del texto».

## Cambiar un parámetro no regenera CSS

Un parámetro es una variable en `<html>`: cambiarla solo hace que el navegador recalcule estilos. El preset de
PrimeVue (unos 140 KB de CSS) se regenera solo al cambiar el modo, el color primario, la superficie o el estilo
(`CLAVES_DEL_PRESET`), y cada estilo base se baja solo cuando se usa.

## Dónde se guarda

En los dos sitios, con papeles distintos (store `apariencia`, `store.ts`):

- **En el navegador** (persistida por usuario, como toda store): es la que se aplica al arrancar, sin esperar a
  la red. El modo se aplica incluso antes de cargar la app (script de `index.html`), sin destello.
- **En la cuenta** (`usuario.apariencia`, JSON): `PUT /api/me/apariencia` al rato de cada cambio
  (un usuario probando opciones manda uno), y llega con `GET /api/me/cuenta`. Así lo elegido sigue al usuario
  en otro equipo, otro navegador o la app móvil. La de la cuenta gana sobre la del navegador, salvo que en el
  navegador haya un cambio sin confirmar (`pendiente`): entonces se manda ese. Lo pendiente sale también antes
  de cerrar sesión.

El backend solo guarda (`App\Cuenta\Apariencia::limpiar`: un objeto de hasta 40 claves con textos cortos,
números o booleanos). Qué ajustes existen lo decide el frontend, que al leer descarta lo que ya no exista
(`normalizar`). Suplantando a otro usuario no se manda nada (y el backend lo prohíbe): lo que cambie quien
suplanta queda en ese navegador.

## Agregar un parámetro

1. Su valor de fábrica en `tema/apariencia.css`.
2. Su entrada en `AJUSTES` (`ajustes.ts`), con `variable`, y su clave en un grupo de `GRUPOS`. Un test compara
   los valores de fábrica de los dos lados.
3. Quien lo use: un token en `tema/tokens.css` o, si es de PrimeVue, una regla en `conectarParametros`.
4. Si el control del editor no es el que le toca por defecto, su entrada en `CONTROL` de `EditorApariencia.vue`
   (y en `COLUMNAS` si es de mosaicos).

Un ajuste que cambie colores del preset (no un parámetro) va sin `variable`: regenera el preset.
