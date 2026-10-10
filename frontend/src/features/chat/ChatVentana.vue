<!--
  El chat como ventana sobre la app (modos esquina y flotante; el centro es
  la página `/chat`). La monta el shell, así que sigue ahí al navegar: se
  conversa mientras se trabaja. Cerrada o minimizada no se desmonta (no se
  pierde lo escrito) pero tampoco marca leído lo que llega.

  Geometría pura en `core/chat/ventana.ts`; aquí GSAP la anima y, en la
  flotante, Draggable la mueve (por la barra) y la redimensiona (bordes).
-->
<template>
  <Teleport to="body">
    <section ref="raiz" class="ventana" :class="[`ventana--${ventana.modo}`, { 'ventana--minimizada': ventana.minimizada, 'ventana--completa': completa }]" role="dialog" aria-label="Mensajes" :aria-hidden="!ventana.abierta" :inert="!ventana.abierta">
      <div class="ventana__marco">
        <BarraChat ref="barra" :arrastrable="arrastrable" />
        <ChatVista class="ventana__cuerpo" :inert="ventana.minimizada" :canal="ventana.canal" :adjuntar="ventana.adjuntar" :activa="ventana.aLaVista" @abrir="ventana.canal = $event" @adjuntado="ventana.adjuntar = null" />
        <span ref="anillo" class="ventana__anillo" aria-hidden="true" />
      </div>
      <template v-if="redimensionable">
        <span v-for="b in BORDES" :key="b" :ref="(el) => (asas[b] = el as HTMLElement | null)" class="asa" :class="`asa--${b}`" aria-hidden="true" />
      </template>
    </section>
  </Teleport>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { useChatStore } from "@/core/chat/store";
import { BORDES, MOVIL, cajaDe, encajar, redimensionar, type Borde, type Caja } from "@/core/chat/ventana";
import BarraChat from "./BarraChat.vue";
import ChatVista from "./ChatVista.vue";
import { useVentanaChat } from "./ventana";

gsap.registerPlugin(Draggable);

const DURACION = 0.38;
const ease = "power3.inOut";

const chat = useChatStore();
const ventana = useVentanaChat();
const raiz = ref<HTMLElement | null>(null);
const anillo = ref<HTMLElement | null>(null);
const barra = ref<InstanceType<typeof BarraChat> | null>(null);
const asas = reactive<Partial<Record<Borde, HTMLElement | null>>>({});
const pantalla = reactive({ w: window.innerWidth, h: window.innerHeight });

const caja = computed(() => cajaDe(ventana, pantalla));
/** Ocupa toda la pantalla (maximizada o en el móvil). */
const completa = computed(() => !ventana.minimizada && caja.value.w === pantalla.w && caja.value.h === pantalla.h);
const arrastrable = computed(() => ventana.modo === "flotante" && !completa.value);
const redimensionable = computed(() => arrastrable.value && !ventana.minimizada && pantalla.w >= MOVIL);

// Mostrar, ocultar y llevar a la caja del estado actual -----------------------
let visible = false;

function aplicar(animar: boolean) {
  const el = raiz.value;
  if (!el) return;
  const c = caja.value;
  const geometria = { x: c.x, y: c.y, width: c.w, height: c.h };
  if (!ventana.abierta) {
    if (visible) gsap.to(el, { autoAlpha: 0, y: c.y + 24, duration: 0.22, ease: "power2.in", overwrite: true });
    else gsap.set(el, { ...geometria, autoAlpha: 0 });
    visible = false;
    return;
  }
  if (!visible) {
    // Aparece en su sitio, subiendo un poco.
    gsap.set(el, { ...geometria, y: c.y + 24, autoAlpha: 0 });
    gsap.to(el, { y: c.y, autoAlpha: 1, duration: animar ? 0.3 : 0, ease: "power3.out", overwrite: true });
    visible = true;
    return;
  }
  if (animar) gsap.to(el, { ...geometria, autoAlpha: 1, duration: DURACION, ease, overwrite: true });
  else gsap.set(el, { ...geometria, autoAlpha: 1 });
}

watch(
  () => [ventana.modo, ventana.abierta, ventana.minimizada, ventana.maximizada],
  () => aplicar(true),
);

function alCambiarPantalla() {
  pantalla.w = window.innerWidth;
  pantalla.h = window.innerHeight;
  if (ventana.flotante) ventana.flotante = encajar(ventana.flotante, pantalla);
  aplicar(false);
}

// "Mire aquí": invocar estando a la vista, o mensaje nuevo con la ventana minimizada.
function llamar() {
  if (!anillo.value || !ventana.abierta) return;
  gsap.fromTo(anillo.value, { opacity: 1 }, { opacity: 0, duration: 1.1, ease: "power2.in", repeat: 1 });
}
watch(() => ventana.llamado, llamar);
watch(
  () => chat.entrante?.n,
  (n) => n && ventana.minimizada && llamar(),
);

// Mover (flotante): Draggable sobre la ventana, con la barra como asa ---------
let mover: Draggable | null = null;

function crearMover() {
  mover?.kill();
  mover = null;
  const el = raiz.value;
  const asa = barra.value?.$el as HTMLElement | undefined;
  if (!el || !asa || !arrastrable.value) return;
  mover = Draggable.create(el, {
    type: "x,y",
    trigger: asa,
    cursor: "grab",
    activeCursor: "grabbing",
    zIndexBoost: false,
    onPress() {
      const r = el.getBoundingClientRect();
      this.applyBounds({ minX: 0, minY: 0, maxX: pantalla.w - r.width, maxY: pantalla.h - r.height });
    },
    onDragEnd() {
      // Minimizada se mueve la barra: la ventana restaurada aparece ahí mismo.
      const base = ventana.flotante ?? caja.value;
      ventana.flotante = encajar(ventana.minimizada ? { ...base, x: this.x, y: this.y } : { ...caja.value, x: this.x, y: this.y }, pantalla);
    },
    onClick() {
      if (ventana.minimizada) ventana.restaurar();
    },
  })[0]!;
}

// Redimensionar (flotante): un Draggable por borde sobre un proxy -------------
let bordes: Draggable[] = [];

function crearBordes() {
  bordes.forEach((d) => d.kill());
  bordes = [];
  raiz.value?.querySelectorAll(".asa__proxy").forEach((p) => p.remove());
  const el = raiz.value;
  if (!el || !redimensionable.value) return;
  for (const b of BORDES) {
    const asa = asas[b];
    if (!asa) continue;
    let inicio: Caja = caja.value;
    let actual: Caja = inicio;
    // En el DOM: Draggable mide su matriz con el offsetParent.
    const proxy = document.createElement("div");
    proxy.className = "asa__proxy";
    el.appendChild(proxy);
    bordes.push(
      Draggable.create(proxy, {
        type: "x,y",
        trigger: asa,
        cursor: getComputedStyle(asa).cursor,
        onPress() {
          inicio = actual = caja.value;
          gsap.set(proxy, { x: 0, y: 0 });
          this.update();
        },
        onDrag() {
          actual = redimensionar(inicio, b, this.x, this.y, pantalla);
          gsap.set(el, { x: actual.x, y: actual.y, width: actual.w, height: actual.h });
        },
        onRelease() {
          if (actual !== inicio) ventana.flotante = actual;
        },
      })[0]!,
    );
  }
}

watch(arrastrable, () => void nextTick(crearMover));
watch(redimensionable, () => void nextTick(crearBordes));

onMounted(() => {
  window.addEventListener("resize", alCambiarPantalla);
  aplicar(false);
  crearMover();
  crearBordes();
});
onBeforeUnmount(() => {
  window.removeEventListener("resize", alCambiarPantalla);
  mover?.kill();
  bordes.forEach((d) => d.kill());
});
</script>

<style scoped>
/* Encima del contenido, el encabezado y los drawers (≤ 100); debajo de los
   overlays y diálogos de PrimeVue (≥ 1000), que el chat también abre. */
/* Las asas van fuera del marco: su recorte (overflow + radio) no las tapa. */
.ventana {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 990;
  visibility: hidden;
}
.ventana__marco {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 1rem;
  background: var(--p-content-background);
  box-shadow:
    0 24px 60px -12px rgb(15 23 42 / 0.35),
    0 0 0 1px rgb(15 23 42 / 0.08);
}
.ventana--esquina.ventana--minimizada .ventana__marco {
  border-radius: 0.9rem 0.9rem 0 0;
}
.ventana--completa .ventana__marco {
  border-radius: 0;
}
.ventana__anillo {
  position: absolute;
  inset: 0;
  z-index: 3;
  border: 2px solid var(--p-primary-color);
  border-radius: inherit;
  opacity: 0;
  pointer-events: none;
}
.ventana__cuerpo {
  flex: 1;
  min-height: 0;
}
.asa {
  position: absolute;
  z-index: 2;
  touch-action: none;
}
:deep(.asa__proxy) {
  position: absolute;
  top: 0;
  left: 0;
  width: 0;
  height: 0;
  visibility: hidden;
}
.asa--n,
.asa--s {
  left: 12px;
  right: 12px;
  height: 10px;
  cursor: ns-resize;
}
.asa--e,
.asa--w {
  top: 12px;
  bottom: 12px;
  width: 10px;
  cursor: ew-resize;
}
.asa--n {
  top: -5px;
}
.asa--s {
  bottom: -5px;
}
.asa--e {
  right: -5px;
}
.asa--w {
  left: -5px;
}
.asa--ne,
.asa--nw,
.asa--se,
.asa--sw {
  width: 18px;
  height: 18px;
}
.asa--ne {
  top: -5px;
  right: -5px;
  cursor: nesw-resize;
}
.asa--sw {
  bottom: -5px;
  left: -5px;
  cursor: nesw-resize;
}
.asa--nw {
  top: -5px;
  left: -5px;
  cursor: nwse-resize;
}
.asa--se {
  bottom: -5px;
  right: -5px;
  cursor: nwse-resize;
}
</style>
