/**
 * Alto y posición de lo que se ve de la página (`visualViewport`) mientras
 * `activo`: en iOS el teclado no achica la página sino que la tapa, y un
 * elemento fijo a pantalla completa dejaría el redactor debajo. Con esto el
 * chat se ajusta al hueco que deja el teclado (en Android lo hace
 * `interactive-widget=resizes-content`, en `index.html`; aquí no cambia nada).
 */
import { onBeforeUnmount, ref, watch, type CSSProperties, type Ref } from 'vue'

export function useAltoVisible(activo: Ref<boolean>): Ref<CSSProperties> {
  const estilo = ref<CSSProperties>({})
  const vista = typeof window !== 'undefined' ? window.visualViewport : null

  function medir() {
    estilo.value = activo.value && vista ? { top: `${vista.offsetTop}px`, height: `${vista.height}px` } : {}
  }

  vista?.addEventListener('resize', medir)
  vista?.addEventListener('scroll', medir)
  watch(activo, medir, { immediate: true })
  onBeforeUnmount(() => {
    vista?.removeEventListener('resize', medir)
    vista?.removeEventListener('scroll', medir)
  })
  return estilo
}
