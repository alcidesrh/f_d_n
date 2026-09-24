<template>
  <div ref="logosEl" class="background">
    <div
      v-for="src in LOGOS"
      :key="src"
      class="img bg-surface-300/50"
      :class="bgClass"
      :style="{ backgroundImage: `url('${src}')` }"
    />
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
 * Fondo decorativo del login: carrusel de fotos a pantalla completa y logos de
 * las empresas rebotando (y chocando con `obstacle`, la tarjeta de login).
 */
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { gsap } from 'gsap'
import { createBouncingBalls } from './bouncingBalls'

const props = defineProps<{ obstacle: HTMLElement | null }>()

const SLIDE_MS = 5000
const SLIDE_COUNT = 10
const LOGOS = [
  'images/logos/copiloto/lapionera5.png',
  'images/logos/copiloto/rosita5.png',
  'images/logos/copiloto/mayadeoro5.png',
  'images/logos/copiloto/starbus5.png',
  'images/logos/copiloto/corporacionlapionera5.png',
]

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
/** Clase según la foto visible: ajusta el color del borde de los logos. */
const bgClass = ref(`login${first}`)

let timer: ReturnType<typeof setInterval> | undefined
let timeline: gsap.core.Timeline | undefined
let balls: ReturnType<typeof createBouncingBalls> | undefined

function advance() {
  const [from, to] = showingA ? [layerA.value, layerB.value] : [layerB.value, layerA.value]
  const next = nextSlide(slides.value[showingA ? 0 : 1])
  slides.value[showingA ? 1 : 0] = next
  preload(nextSlide(next))

  gsap.set(from, { clearProps: 'transform' })
  gsap.set(to, { opacity: 0, clearProps: 'transform' })
  timeline = gsap
    .timeline({
      onComplete: () => {
        showingA = !showingA
        gsap.set(from, { opacity: 0, clearProps: 'transform' })
        bgClass.value = `login${next}`
      },
    })
    .to(
      from,
      { y: -280, rotation: 5, scale: 0.95, opacity: 0, duration: 0.4, ease: 'back.in(1.7)' },
      0,
    )
    .to(to, { opacity: 1, duration: 0.25, ease: 'power2.out' }, 0.05)
    .call(() => balls?.scatter(), [], 0.12)
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
  width: 100vw;
  height: 100vh;
  position: absolute;
  z-index: 2;
}
.bg-layer {
  position: absolute;
  width: 100vw;
  min-height: 100vh;
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
  border: 20px solid var(--p-surface-100);
  border-radius: 999px;
  padding: 20px;
  position: absolute;
  user-select: none;
  z-index: 1;
  background-repeat: no-repeat;
  background-position: center;
  background-size: contain;
  margin: 0;
  &.login1,
  &.login2,
  &.login3,
  &.login8,
  &.login9 {
    border-color: var(--p-surface-900);
  }
  /* El borde cambia en cascada, un logo tras otro. */
  &:nth-child(1) {
    transition:
      border 0.2s 0.5s,
      background-color 0.2s 0.5s;
  }
  &:nth-child(2) {
    transition:
      border 0.2s 0.7s,
      background-color 0.2s 0.7s;
  }
  &:nth-child(3) {
    transition:
      border 0.2s 0.9s,
      background-color 0.2s 0.9s;
  }
  &:nth-child(4) {
    transition:
      border 0.2s 1.12s,
      background-color 0.2s 1.12s;
  }
  &:nth-child(5) {
    transition:
      border 0.2s 1.24s,
      background-color 0.2s 1.24s;
  }
}
</style>
