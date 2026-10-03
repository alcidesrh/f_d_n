<template>
  <div ref="logosEl" class="background">
    <div v-for="n in WHEELS" :key="n" class="img" :class="bgClass">
      <span class="tread" />
    </div>
  </div>
  <div class="overlay" />
  <div
    ref="layerA"
    class="bg-layer"
    :style="{ backgroundImage: `url('${imageUrl(slides[0])}')` }"
  />
  <div
    ref="layerB"
    class="bg-layer"
    :style="{ backgroundImage: `url('${imageUrl(slides[1])}')` }"
  />
</template>

<script setup lang="ts">
/**
 * Fondo decorativo del login: carrusel de fotos a pantalla completa y ruedas de bus rebotando (y chocando con `obstacle`, la tarjeta de login).
 */
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { gsap } from 'gsap'
import { createBouncingBalls } from './bouncingBalls'

const props = defineProps<{ obstacle: HTMLElement | null }>()

const SLIDE_MS = 5000
const SLIDE_COUNT = 10
const WHEELS = 5

const imageUrl = (n: number) => `images/login${n}.png`
const nextSlide = (n: number) => (n % SLIDE_COUNT) + 1
const preload = (n: number) => (new Image().src = imageUrl(n))

const logosEl = useTemplateRef<HTMLElement>('logosEl')
const layerA = useTemplateRef<HTMLElement>('layerA')
const layerB = useTemplateRef<HTMLElement>('layerB')

const first = Math.floor(Math.random() * SLIDE_COUNT) + 1
/** Foto de cada capa; se alternan: una visible y la otra preparada con la siguiente. */
const slides = ref<[number, number]>([first, nextSlide(first)])
let showingA = true
/** Clase según la foto visible: ajusta el color de las ruedas. */
const bgClass = ref(`login${first}`)

let timer: ReturnType<typeof setInterval> | undefined
let timeline: gsap.core.Timeline | undefined
let balls: ReturnType<typeof createBouncingBalls> | undefined

function advance() {
  const [from, to] = showingA ? [layerA.value, layerB.value] : [layerB.value, layerA.value]
  const next = nextSlide(slides.value[showingA ? 0 : 1])
  slides.value[showingA ? 1 : 0] = next
  preload(nextSlide(next))

  // Truco del mantel: la foto actual se jala hacia una dirección al azar, despacio y de
  // pronto muy rápido; la nueva queda debajo. Las ruedas reciben su empujón en ese tirón.
  const angle = Math.random() * Math.PI * 2
  const reach = Math.hypot(window.innerWidth, window.innerHeight)
  gsap.set(from, { zIndex: 1, opacity: 1, x: 0, y: 0 })
  gsap.set(to, { zIndex: 0, opacity: 1, x: 0, y: 0 })
  timeline = gsap
    .timeline({
      onComplete: () => {
        showingA = !showingA
        gsap.set(from, { opacity: 0, clearProps: 'transform' })
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
        bgClass.value = `login${next}`
        balls?.scatter()
      },
      [],
      2.6,
    )
}

onMounted(() => {
  preload(slides.value[1])
  preload(nextSlide(slides.value[1]))
  layerB.value!.style.opacity = '0'
  balls = createBouncingBalls([...logosEl.value!.children] as HTMLElement[], () =>
    props.obstacle?.getBoundingClientRect(),
  )
  balls.start()
  timer = setInterval(advance, SLIDE_MS)
})

onBeforeUnmount(() => {
  clearInterval(timer)
  timeline?.kill()
  balls?.stop()
  gsap.killTweensOf([layerA.value, layerB.value])
})
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 2;
  background: rgb(0 0 0 / 0.55);
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
  background: radial-gradient(
    circle closest-side,
    #aeb4bc 0 5%,
    #2b2f35 5% 7%,
    #6c727a 7% 9%,
    #1a1c20 9% 30%,
    #5a6069 30% 32%,
    #2a2d33 32% 56%,
    #b8bec6 56% 58%,
    #3b3f46 58% 60%,
    color-mix(in srgb, var(--tone) 60%, black) 60% 63%,
    color-mix(in srgb, var(--tone) 75%, white) 63% 64%,
    var(--tone) 64% 80%,
    color-mix(in srgb, var(--tone) 75%, white) 80% 81%,
    color-mix(in srgb, var(--tone) 85%, black) 81% 93%,
    color-mix(in srgb, var(--tone) 70%, black) 93% 94%,
    color-mix(in srgb, var(--tone) 55%, black) 94% 100%
  );
  box-shadow: 0 0 14px 2px rgb(0 0 0 / 0.45);
  user-select: none;
  z-index: 1;
  margin: 0;
  /* Cubo: acabado metálico cepillado. */
  &::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: conic-gradient(#4b5058, #9aa0a8, #3a3e44, #b4bac2, #40444b, #8d939b, #4b5058);
    mask: radial-gradient(circle closest-side, transparent 9%, #000 9.5% 29.5%, transparent 30%);
  }
  /* Seis tuercas alrededor del cubo. */
  &::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
      radial-gradient(circle 5px at 70% 50%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px),
      radial-gradient(circle 5px at 60% 67.3%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px),
      radial-gradient(circle 5px at 40% 67.3%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px),
      radial-gradient(circle 5px at 30% 50%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px),
      radial-gradient(circle 5px at 40% 32.7%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px),
      radial-gradient(circle 5px at 60% 32.7%, #e5e8ec 0 2px, #1a1a1a 3px 4px, transparent 5px);
  }
  /* Dibujo de la banda de rodadura: muescas finas en el borde. */
  .tread {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: repeating-conic-gradient(
      color-mix(in srgb, var(--tone) 60%, white) 0 1.2deg,
      transparent 1.2deg 5deg
    );
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
