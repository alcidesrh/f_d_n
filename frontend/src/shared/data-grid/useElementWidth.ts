import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/**
 * Ancho (px) de un elemento, en vivo. El grid decide tabla o tarjetas por el
 * ancho de su contenedor, no del viewport: el mismo listado vive en una
 * página entera y en el panel angosto del chat.
 */
export function useElementWidth(target: Ref<HTMLElement | null>) {
  const width = ref(0)
  let observer: ResizeObserver | null = null

  onMounted(() => {
    const el = target.value
    if (!el) return
    width.value = el.getBoundingClientRect().width
    if (typeof ResizeObserver === 'undefined') return
    observer = new ResizeObserver(([entry]) => {
      if (entry) width.value = entry.contentRect.width
    })
    observer.observe(el)
  })
  onBeforeUnmount(() => observer?.disconnect())

  return width
}
