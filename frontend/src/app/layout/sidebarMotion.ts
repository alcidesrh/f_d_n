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
 * reacomoda al empezar y al desplegarse al terminar.
 *
 * Móvil (debajo de `lg`): el panel es un drawer que se desliza con `xPercent`.
 * En escritorio el `transform` se elimina: un ancestro transformado volvería
 * relativos los rótulos `position: fixed` del modo `mini`.
 */
import { gsap } from "gsap";
import { nextTick, onBeforeUnmount, onMounted, watch, type Ref } from "vue";
import { useUiStore } from "@/app/ui";
import type { SidebarStore } from "./sidebarStore";

const DURATION = 0.3;
const EASE = "power4.inOut";
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

/**
 * Por lado, compartido: un panel recién montado (p. ej. el de una ruta) arranca
 * del ancho del anterior. `px`: ancho visible del panel; `layout`: margen de `.main`.
 */
const widths: Record<"left" | "right", { px: number; layout: number; ready: boolean }> = {
  left: { px: 250, layout: 250, ready: false },
  right: { px: 250, layout: 250, ready: false },
};

/** Desplaza `.main` para que su borde siga al de cada panel (sin transform si coinciden). */
/** Reproduce un tween pausado con el reloj de GSAP, sin saltos (ver `MAX_STEP_MS`). Devuelve cómo pararlo. */
function playSmooth(tween: gsap.core.Tween): () => void {
  const tick = (_time: number, deltaMs: number) => {
    tween.time(Math.min(tween.duration(), tween.time() + Math.min(deltaMs, MAX_STEP_MS) / 1000));
    if (tween.progress() >= 1) gsap.ticker.remove(tick);
  };
  gsap.ticker.add(tick);
  return () => gsap.ticker.remove(tick);
}

function placeMain(main: HTMLElement) {
  const dx = widths.left.px - widths.left.layout - (widths.right.px - widths.right.layout);
  main.style.transform = dx ? `translate3d(${dx}px, 0, 0)` : "";
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
        // Estructura de `open` con el pliegue en 1: se ve igual que `mini`.
        state.c = 1;
        store.morphing = true;
        store.shown = "open";
        await nextTick();
        if (gen !== generation) return;
        beginFold(el);
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
          store.shown = "mini";
          await nextTick();
          if (gen === generation) endFold(el);
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
    // Contenido: reacomodado al menor de los dos anchos (si el panel se pliega, ya) y
    // desplazado mientras tanto; al terminar, al ancho final.
    const target = store.width;
    setLayout(Math.min(shared.layout, state.px, target));
    main.style.willChange = "transform";
    render();
    modeTween = gsap.to(state, {
      px: target,
      c: targetC,
      h: to === "close" ? 1 : 0,
      duration: DURATION,
      // Interrumpido ya va en movimiento: arrancar desde reposo se sentiría como un tirón.
      ease: interrupted ? "power1.out" : EASE,
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
