/**
 * Estado de la ventana del chat (persistido): modo, abierta, minimizada,
 * maximizada, la caja de la flotante y la última conversación. El ícono del
 * encabezado la vuelve a mostrar tal como estaba. Geometría pura en
 * `core/chat/ventana.ts`.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { LocationQuery, RouteParams } from 'vue-router'
import type { Referencia } from '@/core/chat/types'
import type { Caja, ModoVentana } from '@/core/chat/ventana'

export const useVentanaChat = defineStore(
  'chat-ventana',
  () => {
    const modo = ref<ModoVentana>('centro')
    /** Solo esquina y flotante: cerrada no se ve (en centro es una página). */
    const abierta = ref(false)
    const minimizada = ref(false)
    const maximizada = ref(false)
    const flotante = ref<Caja | null>(null)
    /** Última conversación en pantalla (la ventana y la página la recuerdan). */
    const canal = ref<number | null>(null)
    /** Registro por adjuntar al abrir (p. ej. "Reportar por chat"); no se persiste. */
    const adjuntar = ref<Referencia | null>(null)
    /** Última página fuera del chat: a ella se vuelve al pasar de centro a ventana. */
    const pagina = ref<string | null>(null)
    /** Sube con cada `invocar()` estando ya a la vista: la ventana llama la atención. */
    const llamado = ref(0)

    const esVentana = computed(() => modo.value !== 'centro')
    /** La conversación se está leyendo (marca leído lo que llega). */
    const aLaVista = computed(() => !esVentana.value || (abierta.value && !minimizada.value))

    function abrir(id: number | null = canal.value, registro: Referencia | null = null) {
      canal.value = id
      adjuntar.value = registro
      abierta.value = true
      minimizada.value = false
    }

    /**
     * El ícono del encabezado. Cerrada: reaparece tal como estaba (modo,
     * minimizada o maximizada). Minimizada a la vista: se restaura. Ya a la
     * vista: se señala.
     */
    function invocar() {
      if (!abierta.value) abierta.value = true
      else if (minimizada.value) minimizada.value = false
      else llamado.value++
    }

    function cambiarModo(nuevo: ModoVentana) {
      if (nuevo === modo.value) return
      modo.value = nuevo
      minimizada.value = false
      abierta.value = nuevo !== 'centro'
    }

    const minimizar = () => esVentana.value && (minimizada.value = true)
    const restaurar = () => (minimizada.value = false)
    const alternarMaximizada = () => {
      maximizada.value = !maximizada.value
      minimizada.value = false
    }
    const cerrar = () => esVentana.value && (abierta.value = false)

    return { modo, abierta, pagina, minimizada, maximizada, flotante, canal, adjuntar, llamado, esVentana, aLaVista, abrir, invocar, cambiarModo, minimizar, restaurar, alternarMaximizada, cerrar }
  },
  { persist: { pick: ['modo', 'abierta', 'minimizada', 'maximizada', 'flotante', 'canal'] } },
)

/** Conversación y registro por adjuntar de una ruta `/chat/:canal?adjuntar=Tipo:id`. */
export function destinoDeRuta(params: RouteParams, query: LocationQuery): { canal: number | null; adjuntar: Referencia | null } {
  const id = Number(params.canal)
  const [tipo, ref] = String(query.adjuntar ?? '').split(':')
  return {
    canal: Number.isInteger(id) && id > 0 ? id : null,
    adjuntar: tipo && Number(ref) > 0 ? { tipo, id: Number(ref) } : null,
  }
}
