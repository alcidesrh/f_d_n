<template>
  <div ref="logosEl" class="background">
    <div v-for="n in WHEELS" :key="n" class="img" :class="bgClass">
      <span class="tread" />
    </div>
  </div>
  <div class="overlay" />
  <div class="clima">
    <div ref="capaLluvia" class="lluvia">
      <div ref="cortina" class="cortina">
        <span v-for="(gota, i) in GOTAS" ref="gotas" :key="i" class="gota" :style="gota" />
      </div>
    </div>
    <div ref="capaAmanecer" class="amanecer">
      <div ref="sol" class="sol">
        <span class="rayos" />
        <span class="rayos" />
      </div>
    </div>
  </div>
  <div ref="layerA" class="bg-layer" :style="{ backgroundImage: `url('${imageUrl(slides[0])}')` }" />
  <div ref="layerB" class="bg-layer" :style="{ backgroundImage: `url('${imageUrl(slides[1])}')` }" />
</template>

<script setup lang="ts">
/**
 * Fondo decorativo del login: carrusel de fotos a pantalla completa y ruedas de bus rebotando (y chocando con `obstacle`, la tarjeta de login).
 * Con las ruedas oscuras amanece; con las claras llueve.
 */
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from "vue";
import { gsap } from "gsap";
import { createBouncingBalls } from "./bouncingBalls";

const props = defineProps<{ obstacle: HTMLElement | null }>();

const SLIDE_MS = 5000;
const SLIDE_COUNT = 10;
const WHEELS = 5;
/** Fotos claras: sobre ellas las ruedas se oscurecen (ver `.img.loginN` en el estilo). */
const FOTOS_CLARAS = new Set([1, 2, 3, 8, 9]);

/** Opacidad de la foto de fondo: con sol se ve más que con lluvia. */
const opacidadFoto = (n: number) => (FOTOS_CLARAS.has(n) ? 0.6 : 0.3);

/** Gotas de lluvia: posición, largo y brillo al azar (la caída la anima GSAP). */
const GOTAS = Array.from({ length: 80 }, () => ({
  left: `${Math.random() * 100}%`,
  "--largo": `${70 + Math.random() * 80}px`,
  opacity: 0.3 + Math.random() * 0.5,
}));

type Clima = "lluvia" | "amanecer";

const imageUrl = (n: number) => `images/login${n}.png`;
const nextSlide = (n: number) => (n % SLIDE_COUNT) + 1;
const preload = (n: number) => (new Image().src = imageUrl(n));

const logosEl = useTemplateRef<HTMLElement>("logosEl");
const layerA = useTemplateRef<HTMLElement>("layerA");
const layerB = useTemplateRef<HTMLElement>("layerB");
const capaLluvia = useTemplateRef<HTMLElement>("capaLluvia");
const capaAmanecer = useTemplateRef<HTMLElement>("capaAmanecer");
const cortina = useTemplateRef<HTMLElement>("cortina");
const gotas = useTemplateRef<HTMLElement[]>("gotas");
const sol = useTemplateRef<HTMLElement>("sol");

const first = Math.floor(Math.random() * SLIDE_COUNT) + 1;
/** Foto de cada capa; se alternan: una visible y la otra preparada con la siguiente. */
const slides = ref<[number, number]>([first, nextSlide(first)]);
let showingA = true;
const visible = ref(first);
/** Clase según la foto visible: ajusta el color de las ruedas. */
const bgClass = computed(() => `login${visible.value}`);
/** Ruedas oscuras: amanece. Ruedas claras: llueve. */
const clima = computed<Clima>(() => (FOTOS_CLARAS.has(visible.value) ? "amanecer" : "lluvia"));

let timer: ReturnType<typeof setInterval> | undefined;
let timeline: gsap.core.Timeline | undefined;
let balls: ReturnType<typeof createBouncingBalls> | undefined;
let climaCtx: gsap.Context | undefined;
/** Bucles de cada clima: solo corren mientras ese clima se ve. */
const bucles: Record<Clima, gsap.core.Animation[]> = { lluvia: [], amanecer: [] };

/** Crea los bucles del clima: gotas cayendo con ráfagas de viento e intensidad variable; rayos que giran y titilan. */
function animarClima() {
  climaCtx = gsap.context(() => {
    gsap.set(cortina.value, { rotation: 12, opacity: 0.3 });
    const caidas = gotas.value!.map((gota) => gsap.fromTo(gota, { yPercent: -100, y: 0 }, { y: () => cortina.value!.offsetHeight, duration: gsap.utils.random(0.1, 0.6), ease: "none", repeat: -1, repeatRefresh: true }).progress(Math.random()));

    const intensidad = { v: 1 };
    bucles.lluvia = [
      ...caidas,
      gsap.to(cortina.value, { rotation: "random(4, 20)", duration: 2.5, ease: "sine.inOut", repeat: -1, repeatRefresh: true }),
      gsap.to(intensidad, {
        v: "random(0.7, 1.4)",
        duration: 3,
        ease: "sine.inOut",
        repeat: -1,
        repeatRefresh: true,
        onUpdate: () => caidas.forEach((caida) => caida.timeScale(intensidad.v)),
      }),
    ];
    const [a, b] = gsap.utils.toArray<HTMLElement>(".rayos", sol.value) as [HTMLElement, HTMLElement];
    bucles.amanecer = [gsap.fromTo(a, { rotation: -5 }, { rotation: 5, duration: 14, ease: "sine.inOut", yoyo: true, repeat: -1 }), gsap.fromTo(b, { rotation: 7 }, { rotation: -7, duration: 21, ease: "sine.inOut", yoyo: true, repeat: -1 }), gsap.to(a, { opacity: "random(0.6, 1)", duration: 3.1, ease: "sine.inOut", repeat: -1, repeatRefresh: true }), gsap.to(b, { opacity: "random(0.3, 1)", duration: 2.2, ease: "sine.inOut", repeat: -1, repeatRefresh: true })];
  });
}

/** Funde al clima `c` junto con las ruedas (mismo retraso que la primera); al amanecer el sol entra por la esquina y abre sus rayos. */
function mostrarClima(c: Clima, inmediato = false) {
  const otro: Clima = c === "lluvia" ? "amanecer" : "lluvia";
  const [entra, sale] = c === "lluvia" ? [capaLluvia.value, capaAmanecer.value] : [capaAmanecer.value, capaLluvia.value];
  const t = inmediato ? 0 : 1;
  climaCtx?.add(() => {
    bucles[c].forEach((bucle) => bucle.resume());
    gsap.to(sale, { opacity: 0, duration: 1.8 * t, delay: 0.5 * t, overwrite: "auto", onComplete: () => bucles[otro].forEach((bucle) => bucle.pause()) });
    gsap.to(entra, { opacity: 1, duration: 1.8 * t, delay: 0.5 * t, overwrite: "auto" });
    if (c === "amanecer") {
      gsap.fromTo(entra, { xPercent: 10, yPercent: -10 }, { xPercent: 0, yPercent: 0, duration: 5 * t, delay: 0.5 * t, ease: "power2.out", overwrite: "auto" });
      gsap.fromTo(sol.value, { scale: 0.4 }, { scale: 1, duration: 5 * t, delay: 0.5 * t, ease: "expo.out", overwrite: "auto" });
    }
  });
}

function advance() {
  const [from, to] = showingA ? [layerA.value, layerB.value] : [layerB.value, layerA.value];
  const next = nextSlide(slides.value[showingA ? 0 : 1]);
  slides.value[showingA ? 1 : 0] = next;
  preload(nextSlide(next));

  // Truco del mantel: la foto actual se jala hacia una dirección al azar, despacio y de
  // pronto muy rápido; la nueva queda debajo. Las ruedas reciben su empujón en ese tirón.
  const angle = Math.random() * Math.PI * 2;
  const reach = Math.hypot(window.innerWidth, window.innerHeight);
  gsap.set(from, { zIndex: 1, x: 0, y: 0 });
  gsap.set(to, { zIndex: 0, opacity: 0.3, x: 0, y: 0 });
  // Si la nueva foto trae sol, se ilumina al quedar a la vista (fuera del timeline: debe acabar antes del próximo tirón).
  gsap.to(to, { opacity: opacidadFoto(next), duration: 2, delay: 2.6, ease: "sine.inOut" });
  timeline = gsap
    .timeline({
      onComplete: () => {
        showingA = !showingA;
        gsap.set(from, { opacity: 0, clearProps: "transform" });
      },
    })
    .to(
      from,
      {
        x: Math.cos(angle) * reach,
        y: Math.sin(angle) * reach,
        duration: 3,
        ease: (t: number) => t ** 14,
      },
      0,
    )
    .call(
      () => {
        visible.value = next;
        balls?.scatter();
      },
      [],
      2.6,
    );
}

onMounted(() => {
  preload(slides.value[1]);
  preload(nextSlide(slides.value[1]));
  gsap.set(layerA.value, { opacity: opacidadFoto(first) });
  gsap.set(layerB.value, { opacity: 0 });
  animarClima();
  mostrarClima(clima.value, true);
  balls = createBouncingBalls([...logosEl.value!.children] as HTMLElement[], () => props.obstacle?.getBoundingClientRect());
  balls.start();
  timer = setInterval(advance, SLIDE_MS);
});

watch(clima, (c) => mostrarClima(c));

onBeforeUnmount(() => {
  climaCtx?.revert();
  clearInterval(timer);
  timeline?.kill();
  balls?.stop();
  gsap.killTweensOf([layerA.value, layerB.value]);
});
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 2;
  background: rgb(0 0 0 / 0.55);
}
/* Clima sobre la foto y bajo las ruedas; cambia junto con ellas (mismo retraso que la primera). */
.clima {
  position: fixed;
  inset: 0;
  z-index: 2;
  overflow: hidden;
  pointer-events: none;
  /* La opacidad de cada capa la anima GSAP (`mostrarClima`). */
  & > div {
    position: absolute;
    inset: 0;
    opacity: 0;
  }
}
/* Lluvia: cielo plomizo y gotas cayendo sesgadas por el viento. */
.lluvia {
  background: linear-gradient(rgb(15 23 42 / 0.4), rgb(30 41 59 / 0.15));
  .cortina {
    position: absolute;
    inset: -10% -20%;
  }
  .gota {
    position: absolute;
    top: 0;
    width: 2px;
    border-radius: 1px;
    height: var(--largo);
    background: var(--p-surface-400);
    /*linear-gradient(transparent, rgb(241 245 249));*/
  }
}
/* Amanecer: resplandor cálido desde la esquina superior derecha y los primeros rayos, que se abren al salir el sol. */
.amanecer {
  background: radial-gradient(circle at 88% 0%, rgb(255 237 179 / 0.5), rgb(251 146 60 / 0.22) 22%, transparent 55%), linear-gradient(to bottom left, rgb(251 113 133 / 0.16), transparent 60%);
  /* El sol, en la esquina superior derecha: punto sin tamaño desde el que salen los rayos (y crecen al amanecer). */
  .sol {
    position: absolute;
    left: 88%;
    top: 0;
  }
  /* Dos abanicos de rayos con distinto paso; giran y titilan por separado. */
  .rayos {
    position: absolute;
    left: -150vmax;
    top: -150vmax;
    width: 300vmax;
    height: 300vmax;
    background: repeating-conic-gradient(rgb(255 236 179 / 0.2) 0 1.5deg, transparent 1.5deg 7deg);
    mask: radial-gradient(circle closest-side, #000, rgb(0 0 0 / 0.5) 18%, transparent 45%);
    & + .rayos {
      background: repeating-conic-gradient(from 3deg, rgb(254 215 170 / 0.14) 0 3deg, transparent 3deg 11deg);
    }
  }
}
.bg-layer {
  position: fixed;
  inset: 0;
  z-index: 1;
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  background-attachment: fixed;
  margin: 0;
  will-change: transform, opacity;
}
.background {
  position: fixed;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: 3;
  padding: 30px;
}
.background .img {
  /*
   * Rueda de bus vista de frente: neumático negro con flanco ancho, aro de
   * acero, disco oscuro y cubo metálico con tuercas. `color` es el tono del
   * neumático (paleta zinc), oscuro o claro según la foto; `outline` su contorno.
   */
  color: #71717a; /* zinc-500: tono del neumático sobre foto oscura */
  outline: 3px solid #f4f4f5; /* zinc-100: contorno */
  /* Cada rueda aclara u oscurece un poco el tono base (`--tone`), sin salir del gris. */
  --tone: currentColor;
  position: absolute;
  box-sizing: border-box;
  border-radius: 50%;
  background: radial-gradient(circle closest-side, #aeb4bc 0 5%, #2b2f35 5% 7%, #6c727a 7% 9%, #1a1c20 9% 30%, #5a6069 30% 32%, #2a2d33 32% 56%, #b8bec6 56% 58%, #3b3f46 58% 60%, color-mix(in srgb, var(--tone) 60%, black) 60% 63%, color-mix(in srgb, var(--tone) 75%, white) 63% 64%, var(--tone) 64% 80%, color-mix(in srgb, var(--tone) 75%, white) 80% 81%, color-mix(in srgb, var(--tone) 85%, black) 81% 93%, color-mix(in srgb, var(--tone) 70%, black) 93% 94%, color-mix(in srgb, var(--tone) 55%, black) 94% 100%);
  box-shadow: 0 0 14px 2px rgb(0 0 0 / 0.45);
  user-select: none;
  z-index: 1;
  margin: 0;
  /* Cubo: acabado metálico cepillado. */
  &::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: conic-gradient(#4b5058, #9aa0a8, #3a3e44, #b4bac2, #40444b, #8d939b, #4b5058);
    mask: radial-gradient(circle closest-side, transparent 9%, #000 9.5% 29.5%, transparent 30%);
  }
  /* Seis tuercas alrededor del cubo. */
  &::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle 5px at 70% 50%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px), radial-gradient(circle 5px at 60% 67.3%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px), radial-gradient(circle 5px at 40% 67.3%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px), radial-gradient(circle 5px at 30% 50%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px), radial-gradient(circle 5px at 40% 32.7%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px), radial-gradient(circle 5px at 60% 32.7%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px);
  }
  /* Dibujo de la banda de rodadura: muescas finas en el borde. */
  .tread {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: repeating-conic-gradient(color-mix(in srgb, var(--tone) 60%, white) 0 1.2deg, transparent 1.2deg 5deg);
    mask: radial-gradient(circle closest-side, transparent 94%, #000 94.5%);
  }
  /* Foto clara: neumático oscuro. Foto oscura: neumático claro. */
  &.login1,
  &.login2,
  &.login3,
  &.login8,
  &.login9 {
    color: #3f3f46; /* zinc-700: sobre foto clara (zinc-600 queda en medio) */
    outline-color: #18181b; /* zinc-900 */
  }
  /* El contorno cambia en cascada, un logo tras otro. */
  &:nth-child(1) {
    --tone: color-mix(in srgb, currentColor 86%, white);
    transition:
      color 0.2s 0.5s,
      outline-color 0.2s 0.5s;
  }
  &:nth-child(2) {
    --tone: color-mix(in srgb, currentColor 88%, black);
    transition:
      color 0.2s 0.7s,
      outline-color 0.2s 0.7s;
  }
  &:nth-child(3) {
    --tone: color-mix(in srgb, currentColor 100%, black);
    transition:
      color 0.2s 0.9s,
      outline-color 0.2s 0.9s;
  }
  &:nth-child(4) {
    --tone: color-mix(in srgb, currentColor 72%, white);
    transition:
      color 0.2s 1.12s,
      outline-color 0.2s 1.12s;
  }
  &:nth-child(5) {
    --tone: color-mix(in srgb, currentColor 76%, black);
    transition:
      color 0.2s 1.24s,
      outline-color 0.2s 1.24s;
  }
}
</style>
