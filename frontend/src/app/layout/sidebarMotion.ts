/**
 * Movimiento de un panel lateral, todo con GSAP (el CSS solo describe estados
 * finales, sin `transition`).
 *
 * Escritorio (desde `lg`): el ancho (`--sb-<lado>-w`, lo leen el panel y `.main`)
 * y el contenido se animan en una misma línea de tiempo. La estructura del menú
 * (`store.shown`: toggles, encabezados, submenús, textos en línea o flotantes) solo
 * cambia cuando el panel ya "se ve" como el modo destino, así no hay saltos:
 *  - `open → mini`: primero el contenido se pliega al aspecto `mini`, luego se cambia `shown`.
 *  - `mini → open`: se cambia `shown` y el contenido se despliega desde el aspecto `mini`.
 *  - `close`: solo el ancho; la estructura cambia con el panel invisible.
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
const EASE = "power2.inOut";
const SHADOW = "0 12px 32px rgb(0 0 0 / 0.2)";
const SHADOW_OFF = "0 12px 32px rgb(0 0 0 / 0)";

/**
 * Ancho animado actual de cada lado (px). Se anima un número y se escribe en
 * `onUpdate`: GSAP no lee estilos del DOM (leerlos al arrancar forzaba un
 * recálculo de estilo en el primer fotograma).
 */
const widths: Record<"left" | "right", { px: number; ready: boolean }> = {
  left: { px: 250, ready: false },
  right: { px: 250, ready: false },
};

/** Propiedades en línea que la animación deja sobre el contenido del menú. */
const CONTENT_CLEAR: Array<[string, string]> = [
  [".menu-text", "opacity,pointerEvents"],
  [".nav-row > .menu-link", "paddingInlineStart"],
  [".nav-toggle", "width,marginInlineStart,opacity"],
  [".nav-section-title", "height,paddingTop,paddingBottom,opacity,overflow"],
  [".nav-tree--nested", "height,opacity,overflow"],
];

export function useSidebarMotion(store: SidebarStore, panel: Ref<HTMLElement | null>) {
  const ui = useUiStore();
  let modeTl: gsap.core.Timeline | null = null;
  let drawerTl: gsap.core.Timeline | null = null;

  const all = (selector: string) => panel.value?.querySelectorAll<HTMLElement>(selector) ?? [];
  const miniPadding = () => (store.mini - 24) / 2;

  function resetContent() {
    for (const [selector, props] of CONTENT_CLEAR) {
      const els = all(selector);
      // GSAP avisa en consola si el target está vacío (p. ej. sin submenús).
      if (els.length) gsap.set(els, { clearProps: props });
    }
  }

  // ---- Escritorio: ancho + contenido -----------------------------------------

  /** Pliega el contenido al aspecto `mini` (lo mismo que dibuja `shown = "mini"`). */
  function collapseContent(tl: gsap.core.Timeline) {
    tl.to(all(".menu-text"), { opacity: 0, duration: DURATION * 0.4, ease: "power1.in" }, 0)
      .to(all(".nav-toggle"), { width: 0, marginInlineStart: 0, opacity: 0, duration: DURATION, ease: EASE }, 0)
      .to(all(".nav-row > .menu-link"), { paddingInlineStart: miniPadding(), duration: DURATION, ease: EASE }, 0)
      .to(all(".nav-section-title"), { height: 0, paddingTop: 0, paddingBottom: 0, opacity: 0, overflow: "hidden", duration: DURATION, ease: EASE }, 0)
      .to(all(".nav-tree--nested"), { height: 0, opacity: 0, overflow: "hidden", duration: DURATION, ease: EASE }, 0);
  }

  /** Despliega el contenido desde el aspecto `mini` hasta el natural. */
  function expandContent(tl: gsap.core.Timeline) {
    tl.from(all(".nav-toggle"), { width: 0, marginInlineStart: 0, opacity: 0, duration: DURATION, ease: EASE, clearProps: "width,marginInlineStart,opacity" }, 0)
      .from(all(".nav-row > .menu-link"), { paddingInlineStart: miniPadding(), duration: DURATION, ease: EASE, clearProps: "paddingInlineStart" }, 0)
      .from(all(".nav-section-title"), { height: 0, paddingTop: 0, paddingBottom: 0, opacity: 0, overflow: "hidden", duration: DURATION, ease: EASE, clearProps: "height,paddingTop,paddingBottom,opacity,overflow" }, 0)
      .from(all(".menu-text"), { opacity: 0, duration: DURATION * 0.6, ease: "power1.out", clearProps: "opacity" }, DURATION * 0.4);
  }

  /** Anima el panel al modo actual del store. `initial`: primer montaje. */
  async function syncMode(initial = false) {
    const el = panel.value;
    const main = document.querySelector<HTMLElement>(".main");
    if (!el || !main) return;
    const side = store.side;
    const name = `--sb-${side}-w`;
    const current = widths[side];
    const write = () => {
      el.style.setProperty(name, `${current.px}px`);
      main.style.setProperty(name, `${current.px}px`);
    };

    modeTl?.kill();
    modeTl = null;
    resetContent();
    el.style.setProperty(`--sb-${side}-open`, `${store.open}px`);

    const to = store.mode;
    const from = store.shown;

    // Primer montaje del lado, o móvil (el ancho no se ve): sin animación.
    if ((initial && !current.ready) || ui.isMobile) {
      current.ready = true;
      current.px = store.width;
      store.shown = to;
      write();
      if (!ui.isMobile) gsap.set(el, { visibility: to === "close" ? "hidden" : "visible" });
      return;
    }
    current.ready = true;
    // Un panel recién montado (p. ej. el de una ruta) arranca del ancho actual.
    write();

    const tl = gsap.timeline({ defaults: { duration: DURATION, ease: EASE } });
    modeTl = tl;
    const flipAtEnd = to === "close" || (from === "open" && to === "mini");

    if (to !== "close") tl.set(el, { visibility: "visible" }, 0);
    if (!flipAtEnd) {
      store.shown = to;
      await nextTick();
      if (modeTl !== tl) return;
    }

    tl.to(current, { px: store.width, onUpdate: write }, 0);
    if (from === "open" && to === "mini") collapseContent(tl);
    else if (from === "mini" && to === "open") expandContent(tl);

    if (flipAtEnd) {
      tl.call(async () => {
        store.shown = to;
        await nextTick();
        resetContent();
        if (to === "close") gsap.set(el, { visibility: "hidden" });
      });
    }
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
    modeTl?.kill();
    drawerTl?.kill();
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
