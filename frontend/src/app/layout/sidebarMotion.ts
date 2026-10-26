/**
 * Movimiento de un panel lateral, todo con GSAP (el CSS solo describe estados,
 * sin `transition`).
 *
 * Escritorio (desde `lg`): un único tween sobre `{ px, c }` por panel:
 *  - `px`: ancho del panel (`--sb-<lado>-w` en el panel).
 *  - `c`: pliegue `open ⇄ mini` (0 = aspecto `open`, 1 = aspecto `mini`). Mientras
 *    dura, el panel lleva `data-sb-morph` y el CSS (`sidebar.css`) deriva de `--sb-c`
 *    todo el contenido: una sola escritura por fotograma, sin estilos por elemento.
 *
 * La estructura del menú (`store.shown`: toggles, encabezados, submenús, textos en
 * línea o flotantes) solo cambia cuando el panel ya "se ve" como el destino:
 *  - `open → mini`: se pliega con la estructura de `open` y al final se cambia `shown`.
 *  - `mini → open`: se cambia `shown` con el pliegue en 1 (idéntico a `mini`) y se despliega.
 *  - `close`: solo el ancho; la estructura cambia con el panel invisible.
 * Lo que en ese instante no coincida del todo (el selector de modo, un ítem, el scroll
 * del menú) tampoco salta: Flip toma su lugar antes del cambio y lo lleva al nuevo.
 * Un cambio a medio camino parte siempre de los valores actuales: nunca salta.
 * En el mismo tween va `h` (0 = panel visible, 1 = cerrado): mueve los botones de
 * la cabecera que lo reabren (`--sb-<lado>-h` en `.app-header`, ver `header.css`),
 * con la misma curva y al mismo tiempo que el panel.
 *
 * El contenido (`.main`) no se reacomoda en cada fotograma (un layout de toda la
 * página por fotograma bajaba páginas grandes a ~30 fps): su margen
 * (`--sb-<lado>-w` en `.main`) cambia una sola vez y en el resto del movimiento
 * sigue al borde del panel con `transform`. El margen es siempre el menor de los
 * dos anchos, así el contenido nunca deja un hueco junto al panel (lo que sobra
 * queda bajo el otro panel o recortado por `.body-row`): al plegarse el panel se
 * reacomoda al empezar y al desplegarse al terminar. Ese reacomodo tampoco salta
 * (`moveContent`): lo que cambia de lugar va directo de donde se veía a donde queda, con
 * la curva y el reloj del panel, así lo alineado al lado contrario se queda quieto.
 *
 * Móvil (debajo de `lg`): el panel es un drawer que se desliza con `xPercent`.
 * En escritorio el `transform` se elimina: un ancestro transformado volvería
 * relativos los rótulos `position: fixed` del modo `mini`.
 */
import { gsap } from "gsap";
import { Flip } from "gsap/Flip";
import { nextTick, onBeforeUnmount, onMounted, watch, type Ref } from "vue";
import { useUiStore } from "@/app/ui";
import type { SidebarStore } from "./sidebarStore";

gsap.registerPlugin(Flip);

const DURATION = 0.2;
const EASE = "ease";
/** Lo que se acomoda con el panel ya quieto (el menú al llegar a `mini`). */
const SETTLE: Motion = { duration: DURATION, ease: "power3.out" };
/**
 * Paso máximo por fotograma (ms). Un fotograma lento (el reacomodo único de `.main`,
 * el render de Vue del clic) cuenta como mucho esto: la animación se demora un
 * instante en lugar de saltar adelante para recuperar el tiempo.
 */
const MAX_STEP_MS = 1000 / 30;
const SHADOW = "0 12px 32px rgb(0 0 0 / 0.2)";
const SHADOW_OFF = "0 12px 32px rgb(0 0 0 / 0)";
const MORPH = "data-sb-morph";
/** Lo que el pliegue lleva a alto 0: miden su alto natural en `--h` al empezar. */
const FOLDING = ".nav-section-title, .nav-tree--nested";
/** Lo que se ve tanto en `open` como en `mini`: Flip lo lleva de una estructura a la otra. */
const FLIPPED = ".sb-modes, nav .menu-item:not(.nav-tree--nested .menu-item), nav .p-divider";
/** En el contenido no se entra aquí: se dibuja con sus propias transformaciones. */
const OPAQUE = "canvas, iframe, video, .leaflet-container";
/** Tope de elementos medidos del contenido: en una página enorme el resto se reacomoda sin animar. */
const MAX_MEASURED = 2500;

type Motion = { duration: number; ease: string };

/**
 * Por lado, compartido: un panel recién montado (p. ej. el de una ruta) arranca
 * del ancho del anterior. `px`: ancho visible del panel; `layout`: margen de `.main`.
 */
const widths: Record<"left" | "right", { px: number; layout: number; ready: boolean }> = {
  left: { px: 250, layout: 250, ready: false },
  right: { px: 250, layout: 250, ready: false },
};

/** Reacomodo del contenido en curso: uno solo, lo comparten los dos paneles (hay un solo `.main`). */
let contentFlip: { tween: gsap.core.Tween; stop: () => void } | null = null;

/** Reproduce una animación pausada con el reloj de GSAP, sin saltos (ver `MAX_STEP_MS`). Devuelve cómo pararla. */
function playSmooth(animation: gsap.core.Animation): () => void {
  const tick = (_time: number, deltaMs: number) => {
    animation.time(Math.min(animation.duration(), animation.time() + Math.min(deltaMs, MAX_STEP_MS) / 1000));
    if (animation.progress() >= 1) gsap.ticker.remove(tick);
  };
  gsap.ticker.add(tick);
  return () => gsap.ticker.remove(tick);
}

/** Desplaza `.main` para que su borde siga al de cada panel (sin transform si coinciden). */
function placeMain(main: HTMLElement) {
  const dx = widths.left.px - widths.left.layout - (widths.right.px - widths.right.layout);
  main.style.transform = dx ? `translate3d(${dx}px, 0, 0)` : "";
}

/** Lleva con Flip lo que `state` tomó del panel antes de cambiar su estructura (solo lo que cambió de lugar). */
function flipPanel(state: Flip.FlipState, motion: Motion) {
  // Escalar en lugar de cambiar el alto: un ítem que creciera empujaría a los de abajo en cada fotograma.
  const flip = Flip.from(state, { ...motion, scale: true, simple: true, prune: true });
  // Con el reloj del panel; dentro de otra línea de tiempo, Flip aún la completa si la interrumpe otro `getState`.
  playSmooth(gsap.timeline({ paused: true }).add(flip));
}

/** Para el reacomodo del contenido en curso y le quita lo que le puso. */
function stopContentFlip() {
  if (!contentFlip) return;
  contentFlip.stop();
  const targets = contentFlip.tween.targets();
  contentFlip.tween.kill();
  gsap.set(targets, { clearProps: "transform" });
  contentFlip = null;
}

/** Si se le puede poner `transform` sin pisar nada: no es fijo, en línea ni lo transforma o anima ya otro. */
function movable(el: HTMLElement): boolean {
  if (gsap.isTweening(el)) return false;
  const css = getComputedStyle(el);
  if (css.display === "inline" || css.position === "fixed" || css.animationName !== "none") return false;
  if (css.transform !== "none" || css.translate !== "none" || css.rotate !== "none" || css.scale !== "none") return false;
  // Una transición de CSS sobre `transform` iría detrás de cada fotograma de GSAP.
  const durations = css.transitionDuration.split(",").map(parseFloat);
  return !css.transitionProperty.split(",").some((p, i) => /^\s*(all|transform|translate)\s*$/.test(p) && (durations[i % durations.length] ?? 0) > 0);
}

/** Dónde se ve un elemento (con lo que lo transforme) y si se ve. */
type Box = { left: number; top: number; width: number; height: number; shown: boolean };

/** Desplazamiento de un elemento al empezar y al final del movimiento. */
type Offset = { from: [number, number]; to: [number, number] };

/** `a` respecto de `b` (de un ancestro que también se desplaza). */
function minus(a: Offset, b: Offset | undefined): Offset {
  return {
    from: [a.from[0] - (b?.from[0] ?? 0), a.from[1] - (b?.from[1] ?? 0)],
    to: [a.to[0] - (b?.to[0] ?? 0), a.to[1] - (b?.to[1] ?? 0)],
  };
}

/** Dónde va a la fracción `t` del movimiento. */
function along(offset: Offset, t: number): [number, number] {
  return [offset.from[0] + (offset.to[0] - offset.from[0]) * t, offset.from[1] + (offset.to[1] - offset.from[1]) * t];
}

/** Lo más lejos que llega de su lugar. */
function reach(offset: Offset): number {
  return Math.max(...[0, 0.5, 1].map((t) => Math.hypot(...along(offset, t))));
}

/** Si no se mueve (menos de 1 px). */
function still(offset: Offset): boolean {
  return [...offset.from, ...offset.to].every((v) => Math.abs(v) < 1);
}

/** Dónde se ve `el` (o `r`, uno de sus renglones de texto). */
function boxOf(el: Element, r = el.getBoundingClientRect()): Box {
  return { left: r.left, top: r.top, width: r.width, height: r.height, shown: !!(r.width || r.height) };
}

/** Si `box`, corrido `by`, pisa a `other` (más de un cuarto del menor en los dos ejes: rozarse no cuenta). */
function covers(box: Box, by: readonly [number, number], other: Box): boolean {
  const x = Math.min(box.left + by[0] + box.width, other.left + other.width) - Math.max(box.left + by[0], other.left);
  const y = Math.min(box.top + by[1] + box.height, other.top + other.height) - Math.max(box.top + by[1], other.top);
  return x > Math.min(box.width, other.width) / 4 && y > Math.min(box.height, other.height) / 4;
}

/** Qué tanto se sale `box`, corrido `by`, de `outer`: la mayor fracción de su ancho o de su alto que queda fuera. */
function spill(box: Box, by: readonly [number, number], outer: Box): number {
  const left = box.left + by[0];
  const top = box.top + by[1];
  const x = Math.max(0, outer.left - left) + Math.max(0, left + box.width - outer.left - outer.width);
  const y = Math.max(0, outer.top - top) + Math.max(0, top + box.height - outer.top - outer.height);
  return Math.max(box.width ? x / box.width : 0, box.height ? y / box.height : 0);
}

/** Si se dibuja con fondo propio (una tarjeta, un botón): tapa limpio lo que cruza, a diferencia del texto suelto. */
function opaque(el: Element): boolean {
  const css = getComputedStyle(el);
  return css.backgroundImage !== "none" || !/^(transparent|rgba\(0, 0, 0, 0\))$/.test(css.backgroundColor);
}

/** Si se ve como una caja (fondo, borde o sombra): lo que se sale de ella se nota. */
function framed(el: Element): boolean {
  const css = getComputedStyle(el);
  return opaque(el) || css.boxShadow !== "none" || ["top", "right", "bottom", "left"].some((side) => parseFloat(css.getPropertyValue(`border-${side}-width`)) > 0);
}

/** Los renglones de texto de un elemento (lo que de verdad se ve de él si no tiene fondo), sin los de lo que `apart` mueve por su cuenta. */
function textLines(el: Element, apart: (inner: Element) => boolean, limit = 40): Box[] {
  const lines: Box[] = [];
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  const range = document.createRange();
  for (let node = walker.nextNode(); node && lines.length < limit; node = walker.nextNode()) {
    if (!node.textContent?.trim()) continue;
    let inner = node.parentElement;
    while (inner && inner !== el && !apart(inner)) inner = inner.parentElement;
    if (inner && inner !== el) continue;
    range.selectNodeContents(node);
    for (const r of range.getClientRects()) lines.push(boxOf(el, r));
  }
  return lines;
}

/** Elementos del contenido a la vista o cerca, en orden de documento, con su caja tal como se ve. */
function measureContent(root: HTMLElement, view: DOMRect): { el: HTMLElement; seen: Box }[] {
  const near = view.height / 2;
  const found: { el: HTMLElement; seen: Box }[] = [];
  const pending: Element[] = [...root.children].reverse();
  while (pending.length && found.length < MAX_MEASURED) {
    const el = pending.pop();
    if (!(el instanceof HTMLElement)) continue;
    const r = el.getBoundingClientRect();
    if (r.width || r.height) {
      if (r.bottom < view.top - near || r.top > view.bottom + near) continue;
    } else {
      const display = getComputedStyle(el).display;
      if (display === "none") continue;
      if (display === "contents") {
        pending.push(...[...el.children].reverse());
        continue;
      }
    }
    found.push({ el, seen: boxOf(el, r) });
    if (!el.matches(OPAQUE) && getComputedStyle(el).display !== "inline") pending.push(...[...el.children].reverse());
  }
  return found;
}

/**
 * Lleva el contenido de `.main` sin saltos de donde se ve a donde quedará al terminar el
 * movimiento del panel, como Flip pero solo la posición (`x`/`y`): el tamaño cambia de
 * una vez (escalar deformaría los textos y animar el ancho volvería a reacomodar la página
 * en cada fotograma). `layout(true)` pone el margen del final y `layout(false)` el de
 * mientras dura; si son distintos (el panel se despliega), el del final solo se mide y se
 * aplica al terminar, cuando el panel ya cubre lo que se encoge. `shift`: lo que se
 * desplaza `.main` hasta el final con el margen de mientras dura. Cada elemento se anima
 * respecto de su ancestro más cercano que se mueve: un bloque lleva lo de adentro y lo que
 * además se reacomoda en él (lo alineado a la derecha, una tarjeta que cambia de columna)
 * anima solo esa diferencia; lo alineado al lado contrario del panel se queda quieto.
 */
function moveContent(main: HTMLElement, layout: (final: boolean) => void, deferred: boolean, shift: number, motion: Motion) {
  const root = main.querySelector<HTMLElement>(".main-inner") ?? main;
  const rows = measureContent(root, main.getBoundingClientRect()).map((row) => ({ ...row, now: row.seen, end: row.seen }));
  stopContentFlip();
  layout(false);
  for (const row of rows) row.now = row.end = boxOf(row.el);
  if (deferred) {
    layout(true);
    for (const row of rows) row.end = boxOf(row.el);
    layout(false);
  }

  // De cada uno, lo que se ve menos lo que dicta el margen: al empezar y al final.
  const own = new Map<Element, Offset>(rows.map(({ el, seen, now, end }) => [el, { from: [seen.left - now.left, seen.top - now.top], to: deferred ? [end.left - now.left - shift, end.top - now.top] : [0, 0] }]));
  const byEl = new Map(rows.map((row) => [row.el, row]));
  const inks = new Map<Element, Box[]>();
  /** El texto de `el` que va con él (sin lo que dentro se mueve aparte ni lo que se dibuja con sus propias reglas, como un mapa). */
  const ink = (el: Element, mine: Offset): Box[] => {
    let lines = inks.get(el);
    if (!lines) {
      lines = textLines(el, (inner) => {
        const theirs = own.get(inner);
        return inner.matches(OPAQUE) || (!!theirs && !still(minus(theirs, mine)));
      });
      inks.set(el, lines);
    }
    return lines;
  };
  /**
   * Si algo sin fondo propio (texto, un enlace, un contenedor transparente) puede deslizarse sin
   * pisar en el camino el texto de un hermano ni salirse de la caja que lo contiene. Si no, se
   * reacomoda de una vez con ellos (el párrafo que baja porque el título de arriba ahora ocupa
   * dos líneas, la flecha de una tarjeta que se angostó). Una tarjeta o un botón no pasan por
   * aquí: tapan limpio lo que cruzan.
   */
  const clear = (el: HTMLElement, now: Box, move: Offset, base: Offset | undefined): boolean => {
    const parent = el.parentElement;
    const container = parent && framed(parent) ? byEl.get(parent)?.now : undefined;
    // Estorba un hermano que se mueve mucho menos (dos que se cruzan rápido en sentidos opuestos,
    // no), salvo si tiene fondo y se dibuja encima (viene después): lo tapa limpio al pasar.
    const far = reach(move) / 2;
    const obstacles = [...(parent?.children ?? [])].flatMap((sibling) => {
      const theirs = sibling !== el ? own.get(sibling) : undefined;
      if (!theirs) return [];
      const rel = minus(theirs, base);
      if (reach(rel) >= far || (el.compareDocumentPosition(sibling) & Node.DOCUMENT_POSITION_FOLLOWING && opaque(sibling))) return [];
      return [{ rel, lines: ink(sibling, theirs) }];
    });
    return ![0, 0.25, 0.5, 0.75, 1].some((t) => {
      const [x, y] = along(move, t);
      if (container && spill(now, [x, y], container) > spill(now, [0, 0], container) + 0.25) return true;
      return obstacles.some(({ rel, lines }) => {
        const [sx, sy] = along(rel, t);
        return lines.some((line) => covers(now, [x - sx, y - sy], line));
      });
    });
  };

  // Cada uno se mueve respecto del ancestro más cercano que se mueve (`carried`: lo que hereda lo
  // de adentro). Dentro de lo que no se mueve (un contenedor transparente) cada uno va por su cuenta.
  const moves = new Map<Element, Offset>();
  const carried = new Map<Element, Offset | undefined>();
  for (const { el, seen, now, end } of rows) {
    const mine = own.get(el);
    if (!mine) continue;
    let base: Offset | undefined;
    for (let p = el.parentElement; p && p !== root; p = p.parentElement) {
      if (!carried.has(p)) continue;
      base = carried.get(p);
      break;
    }
    const move = minus(mine, base);
    // Lo que aparece o desaparece no tiene de dónde partir.
    const moving = !still(move) && seen.shown && now.shown && end.shown && movable(el) && (opaque(el) || clear(el, now, move, base));
    if (moving) moves.set(el, move);
    carried.set(el, moving ? mine : base);
  }
  if (!moves.size) return;
  const moveOf = (el: Element) => moves.get(el) ?? { from: [0, 0], to: [0, 0] };
  const tween = gsap.fromTo(
    [...moves.keys()],
    { x: (_: number, el: Element) => moveOf(el).from[0], y: (_: number, el: Element) => moveOf(el).from[1] },
    {
      x: (_: number, el: Element) => moveOf(el).to[0],
      y: (_: number, el: Element) => moveOf(el).to[1],
      ...motion,
      paused: true,
      // Al desplegarse termina junto con el panel, que en ese instante aplica el margen final.
      clearProps: "transform",
      onComplete: () => {
        if (contentFlip?.tween === tween) contentFlip = null;
      },
    },
  );
  contentFlip = { tween, stop: playSmooth(tween) };
}

export function useSidebarMotion(store: SidebarStore, panel: Ref<HTMLElement | null>) {
  const ui = useUiStore();
  const shared = widths[store.side];
  const varName = `--sb-${store.side}-w`;
  /** Lo que anima GSAP; `render` lo vuelca al DOM. */
  const state = { px: shared.px, c: 0, h: store.mode === "close" ? 1 : 0 };
  let writtenC = -1;
  let writtenH = -1;
  let main: HTMLElement | null = null;
  let header: HTMLElement | null = null;
  let modeTween: gsap.core.Tween | null = null;
  let stopTick: (() => void) | null = null;
  let drawerTl: gsap.core.Timeline | null = null;
  /** Descarta continuaciones (`await nextTick()`) de un cambio de modo ya superado. */
  let generation = 0;

  // ---- Escritorio: ancho + pliegue -------------------------------------------

  function render() {
    const el = panel.value;
    if (!el) return;
    shared.px = state.px;
    el.style.setProperty(varName, `${state.px}px`);
    if (main) placeMain(main);
    if (header && state.h !== writtenH) {
      writtenH = state.h;
      header.style.setProperty(`--sb-${store.side}-h`, String(state.h));
    }
    // `--sb-c` se hereda dentro del panel: solo se escribe si cambia (en `close` no).
    if (state.c !== writtenC) {
      writtenC = state.c;
      el.style.setProperty("--sb-c", String(state.c));
    }
  }

  /** Activa el pliegue sobre la estructura de `open` (si no lo estaba ya). */
  function beginFold(el: HTMLElement) {
    store.morphing = true;
    if (el.hasAttribute(MORPH)) return;
    // Rótulos flotantes de `mini` que hayan quedado a medias.
    const texts = el.querySelectorAll<HTMLElement>(".menu-text");
    if (texts.length) {
      gsap.killTweensOf(texts);
      gsap.set(texts, { clearProps: "opacity,pointerEvents" });
    }
    // Primero todas las lecturas, luego las escrituras: un solo cálculo de layout.
    const folding = [...el.querySelectorAll<HTMLElement>(FOLDING)];
    const heights = folding.map((node) => node.offsetHeight);
    folding.forEach((node, i) => node.style.setProperty("--h", `${heights[i]}px`));
    render();
    el.setAttribute(MORPH, "");
  }

  /** Margen de `.main` de este lado: lo único que reacomoda el contenido. */
  function setLayout(px: number) {
    // Un `.main` nuevo (el shell se montó otra vez) aún no lo tiene en línea.
    if (!main || (px === shared.layout && main.style.getPropertyValue(varName))) return;
    shared.layout = px;
    main.style.setProperty(varName, `${px}px`);
    placeMain(main);
  }

  function endFold(el: HTMLElement) {
    el.removeAttribute(MORPH);
    state.c = 0;
    store.morphing = false;
  }

  /** Coloca el panel en su modo sin animar (primer montaje, móvil). */
  function snap(el: HTMLElement) {
    shared.ready = true;
    state.px = store.width;
    state.h = store.mode === "close" ? 1 : 0;
    store.shown = store.mode;
    endFold(el);
    setLayout(state.px);
    render();
    if (!ui.isMobile) el.style.visibility = store.mode === "close" ? "hidden" : "visible";
  }

  /** Anima el panel al modo actual del store. `mounted`: el panel acaba de montarse. */
  async function syncMode(mounted = false) {
    const el = panel.value;
    main = document.querySelector<HTMLElement>(".main");
    header = document.querySelector<HTMLElement>(".app-header");
    if (!el || !main) return;

    const interrupted = !!modeTween && modeTween.progress() > 0 && modeTween.progress() < 1;
    stopMode();
    const gen = ++generation;
    el.style.setProperty(`--sb-${store.side}-open`, `${store.open}px`);

    // Primer panel de este lado, o móvil (el ancho no se ve): sin animación.
    if ((mounted && !shared.ready) || ui.isMobile) return snap(el);

    const to = store.mode;
    // Interrumpido ya va en movimiento: arrancar desde reposo se sentiría como un tirón.
    const motion: Motion = { duration: DURATION, ease: interrupted ? "power1.out" : EASE };
    let targetC = state.c;
    let onDone: (() => void) | undefined;

    if (mounted) {
      // Panel nuevo (cambio de ruta, vuelta de móvil): la estructura del destino ya,
      // solo el ancho se anima desde el del panel anterior.
      store.shown = to;
      endFold(el);
      render();
      if (to === "close") onDone = () => (el.style.visibility = "hidden");
    } else if (to === "open") {
      if (store.shown === "mini") {
        // Estructura de `open` con el pliegue en 1: se ve igual que `mini`; lo que no, lo lleva Flip.
        const before = Flip.getState(el.querySelectorAll(FLIPPED), { simple: true });
        state.c = 1;
        store.morphing = true;
        store.shown = "open";
        await nextTick();
        if (gen !== generation) return;
        beginFold(el);
        flipPanel(before, motion);
      } else {
        store.shown = "open";
      }
      targetC = 0;
      onDone = () => endFold(el);
    } else if (to === "mini") {
      if (store.shown === "close") {
        // Desde oculto: la estructura de `mini` directamente.
        endFold(el);
        store.shown = "mini";
      } else if (store.shown === "open") {
        beginFold(el);
        targetC = 1;
        onDone = async () => {
          const before = Flip.getState(el.querySelectorAll(FLIPPED), { simple: true });
          store.shown = "mini";
          await nextTick();
          if (gen !== generation) return;
          endFold(el);
          flipPanel(before, SETTLE);
        };
      }
    } else {
      onDone = async () => {
        el.style.visibility = "hidden";
        store.shown = "close";
        await nextTick();
        if (gen === generation) endFold(el);
      };
    }

    if (to !== "close") el.style.visibility = "visible";
    // Contenido: con el menor de los dos anchos mientras dura (si el panel se pliega, ya)
    // y desplazado para seguir su borde; al terminar, con el ancho final. Lo que se mueve
    // dentro va directo a donde quedará, con la curva y el reloj del panel.
    const target = store.width;
    const during = Math.min(shared.layout, state.px, target);
    const layout = (final: boolean) => {
      // El del final solo se mide: con el panel ya en su ancho de destino.
      shared.px = final ? target : state.px;
      setLayout(final ? target : during);
    };
    moveContent(main, layout, during !== target, (target - state.px) * (store.side === "left" ? 1 : -1), motion);
    main.style.willChange = "transform";
    render();
    modeTween = gsap.to(state, {
      px: target,
      c: targetC,
      h: to === "close" ? 1 : 0,
      ...motion,
      paused: true,
      onUpdate: render,
      onComplete: () => {
        setLayout(target);
        if (main) {
          placeMain(main);
          main.style.willChange = "";
        }
        onDone?.();
      },
    });
    stopTick = playSmooth(modeTween);
  }

  function stopMode() {
    stopTick?.();
    stopTick = null;
    modeTween?.kill();
    modeTween = null;
  }

  // ---- Móvil: drawer -----------------------------------------------------------

  function placeDrawer(animate: boolean) {
    const el = panel.value;
    if (!el) return;
    const open = store.drawer;
    const hidden = store.side === "left" ? -100 : 100;
    drawerTl?.kill();
    if (!animate) {
      gsap.set(el, { xPercent: open ? 0 : hidden, visibility: open ? "visible" : "hidden", boxShadow: open ? SHADOW : SHADOW_OFF });
      return;
    }
    drawerTl = gsap.timeline();
    if (open) {
      drawerTl.set(el, { visibility: "visible" }).to(el, { xPercent: 0, boxShadow: SHADOW, duration: DURATION, ease: "power2.out" });
    } else {
      drawerTl.to(el, { xPercent: hidden, boxShadow: SHADOW_OFF, duration: DURATION * 0.8, ease: "power2.in" }).set(el, { visibility: "hidden" });
    }
  }

  function placeDocked() {
    const el = panel.value;
    if (!el) return;
    drawerTl?.kill();
    gsap.set(el, { clearProps: "transform,boxShadow", visibility: store.mode === "close" ? "hidden" : "visible" });
  }

  const place = () => (ui.isMobile ? placeDrawer(false) : placeDocked());

  // ---- Rótulos flotantes del modo `mini` ----------------------------------------

  function label(event: Event, visible: boolean) {
    if (!store.compact) return;
    const link = (event.target as Element | null)?.closest(".menu-link");
    if (!link) return;
    const related = (event as MouseEvent | FocusEvent).relatedTarget as Node | null;
    if (!visible && related && link.contains(related)) return;
    const text = link.querySelector(".menu-text");
    if (!text) return;
    gsap.to(text, {
      opacity: visible ? 1 : 0,
      pointerEvents: visible ? "auto" : "none",
      duration: visible ? DURATION : DURATION * 0.5,
      ease: "power1.out",
      overwrite: "auto",
    });
  }

  const onOver = (e: Event) => label(e, true);
  const onOut = (e: Event) => label(e, false);

  onMounted(() => {
    void syncMode(true);
    place();
    const el = panel.value;
    el?.addEventListener("mouseover", onOver);
    el?.addEventListener("mouseout", onOut);
    el?.addEventListener("focusin", onOver);
    el?.addEventListener("focusout", onOut);
  });

  onBeforeUnmount(() => {
    stopMode();
    drawerTl?.kill();
    generation++;
    // El store sobrevive al panel: que el siguiente no herede un pliegue a medias.
    store.morphing = false;
    if (store.shown !== store.mode) store.shown = store.mode;
    const el = panel.value;
    el?.removeEventListener("mouseover", onOver);
    el?.removeEventListener("mouseout", onOut);
    el?.removeEventListener("focusin", onOver);
    el?.removeEventListener("focusout", onOut);
  });

  watch(
    () => store.mode,
    () => void syncMode(),
  );
  watch(
    () => store.drawer,
    (open) => {
      if (!ui.isMobile) return;
      placeDrawer(true);
      if (open) void nextTick(() => panel.value?.querySelector<HTMLElement>(".close-sidebar")?.focus());
    },
  );
  watch(
    () => ui.isMobile,
    () => {
      void syncMode(true);
      place();
    },
  );
}
