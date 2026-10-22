/**
 * Autorizaciones del usuario con sesión (ADR-040): el resumen de la cabecera
 * (las que espera resolver y sus pases aprobados sin usar), el bloqueo que se
 * le está ofreciendo convertir en solicitud (lo muestra
 * `shared/autorizacion/SolicitarAutorizacionDialog`, montado en el shell) y
 * un contador de cambios que suben los avisos por Mercure (`tipo:
 * 'autorizacion'` en el tópico privado del chat) para que las pantallas
 * abiertas se refresquen.
 */
import { defineStore } from 'pinia'
import { ref, shallowRef } from 'vue'
import { fetchResumen } from './api'
import { bloqueoAutorizable } from './reglas'
import type { Autorizable, ResumenAutorizaciones } from './types'

export const useAutorizacionesStore = defineStore('autorizaciones', () => {
  const resumen = shallowRef<ResumenAutorizaciones | null>(null)
  /** El bloqueo que se ofrece pedir como autorización (null: el diálogo está cerrado). */
  const ofrecida = shallowRef<{ autorizable: Autorizable; mensaje: string } | null>(null)
  /** Sube con cada aviso: las pantallas que listan autorizaciones lo miran para volver a pedirlas. */
  const cambios = ref(0)

  async function cargarResumen() {
    try {
      resumen.value = await fetchResumen()
    } catch {
      // Sin resumen la campana queda sin número; no es un error para el usuario.
    }
  }

  /**
   * Si el error es un bloqueo que admite autorización, ofrece pedirla y
   * devuelve true (quien llama puede omitir su propio aviso de error).
   */
  function ofrecer(error: unknown): boolean {
    const bloqueo = bloqueoAutorizable(error)
    if (!bloqueo) return false
    ofrecida.value = bloqueo
    return true
  }

  /** Ofrecer pedirla sin haber intentado la operación (p. ej. sin el permiso). */
  function ofrecerDirecto(autorizable: Autorizable, mensaje: string) {
    ofrecida.value = { autorizable, mensaje }
  }

  function cerrar() {
    ofrecida.value = null
  }

  /** Llegó un aviso de una autorización (pedida, resuelta, retirada). */
  function alAvisar() {
    cambios.value++
    void cargarResumen()
  }

  return { resumen, ofrecida, cambios, cargarResumen, ofrecer, ofrecerDirecto, cerrar, alAvisar }
})
