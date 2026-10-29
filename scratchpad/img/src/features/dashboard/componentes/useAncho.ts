import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/** Ancho en px de un elemento, al día con ResizeObserver (las gráficas SVG se dibujan a su ancho real). */
export function useAncho(el: Ref<HTMLElement | null>, inicial = 600) {
  const ancho = ref(inicial)
  let observador: ResizeObserver | null = null
  onMounted(() => {
    if (!el.value) return
    ancho.value = el.value.clientWidth || inicial
    observador = new ResizeObserver(([e]) => {
      if (e) ancho.value = Math.max(1, Math.round(e.contentRect.width))
    })
    observador.observe(el.value)
  })
  onBeforeUnmount(() => observador?.disconnect())
  return ancho
}
