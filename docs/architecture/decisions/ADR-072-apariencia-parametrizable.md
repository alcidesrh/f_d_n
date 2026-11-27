# ADR-072: Apariencia parametrizable y guardada en la cuenta

**Estado:** Aceptada (amplía el tema claro y oscuro con rampas invertidas que ya usaba el frontend)

## Contexto

El CSS global estaba en `frontend/src/assets/` sin un orden que dijera qué depende de qué: tokens propios con
alias repetidos (`--text`, `--text-color`, `--border`, `--bg`, `--bg2`), reglas sin uso y medidas escritas a
mano. El tema vivía repartido entre la store `ui` (modo, colores y estilo, junto con el viewport), `theme.ts`,
`App.vue` (la clase de `<html>`) y el editor de Mi cuenta, que solo ofrecía modo, color primario, superficie y
estilo. Lo elegido se guardaba solo en el navegador: en un puesto compartido o en la app móvil el usuario no
encontraba su apariencia.

## Decisión

**CSS en tres niveles** (`frontend/src/styles/`, detalle en `docs/frontend/apariencia.md`): parámetros
`--apariencia-*` (los rasgos generales: tamaño y familia de la letra, esquinas, densidad, sombras, animaciones,
contraste y fondo), tokens (los de PrimeVue, las rampas de Tailwind que leen de ellos y los de la app, escritos
con los parámetros) y consumidores (elementos, shell, ajustes de PrimeVue y utilidades). El CSS de un solo
componente queda en su `<style>`.

**El preset de PrimeVue se conecta a los parámetros al generarlo** (`conectarParametros`): radios, rellenos,
opacidades de sombra y duraciones literales del estilo base pasan a `calc(valor * var(--apariencia-*))`. Así un
parámetro cambia con una variable en `<html>`, sin regenerar el CSS de PrimeVue; solo el modo, los colores y el
estilo lo regeneran. Con los valores de fábrica todo mide lo mismo que antes.

**Un catálogo puro** (`app/apariencia/ajustes.ts`) describe cada ajuste: opciones, valor de fábrica y
parámetro CSS. De él salen la store (`useAparienciaStore`, que reemplaza la parte de tema de `ui`), la lectura de
lo guardado y el editor de Mi cuenta, que muestra todos los ajustes.

**Se guarda en el navegador y en la cuenta.** La copia del navegador (store persistida por usuario) se aplica al
arrancar, sin esperar a la red, y el modo antes de cargar la app. La de la cuenta (`usuario.apariencia`, JSON;
`PUT /api/me/apariencia`, se lee con `GET /api/me/cuenta`) hace que la apariencia siga al usuario en otro
equipo, otro navegador o la app móvil, y gana al llegar, salvo que haya un cambio local sin confirmar: entonces
se manda este. Los cambios salen agrupados (uno al rato del último) y lo pendiente, antes de cerrar sesión. El
backend no conoce las opciones: guarda un objeto acotado (`App\Cuenta\Apariencia`) y el frontend descarta al
leer lo que ya no exista. Suplantando no se manda nada y `PUT /api/me/apariencia` es de las rutas que nunca se
hacen como otro (ADR-054).

## Consecuencias

- Un literal (`border-radius: 6px`, `transition: .2s`) no sigue a la apariencia; los tokens sí. La convención
  está en `frontend/AGENTS.md`.
- La primera vez en un equipo nuevo se ve la apariencia de fábrica hasta que llega la cuenta (un instante tras
  entrar); después, la del navegador ya es la del usuario.
- Agregar un ajuste es una entrada en el catálogo y su parámetro; el backend no cambia.
- Lo guardado con la versión anterior (store `ui`) se lee una vez, sube a la cuenta y se borra.

## Alternativas descartadas

- **Solo en el navegador** (como antes): no sigue al usuario entre equipos ni a la app móvil.
- **Solo en la base**: cada arranque esperaría a la red para pintar (destello o pantalla en blanco) y sin
  conexión la app perdería la apariencia.
- **Regenerar el preset para cada ajuste**: cada cambio volvería a escribir unos 140 KB de CSS; con variables el
  navegador solo recalcula estilos.
- **Validar las opciones en el backend**: duplicaría el catálogo en PHP y obligaría a desplegar los dos lados
  para cada ajuste nuevo.
