/**
 * Qué puede hacer el usuario con los boletos (anular, reasignar): lo decide
 * el backend (`GET /venta/boletos/permisos`), que conoce al administrador. Se
 * pide una vez por sesión de la página.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { fetchPermisosBoleto } from './api'
import type { PermisosBoleto } from './types'

export const usePermisosBoleto = defineStore('permisos-boleto', () => {
  const permisos = ref<PermisosBoleto | null>(null)
  let pedido: Promise<void> | null = null

  /** Pide los permisos si aún no están; no falla (sin permisos = sin opciones). */
  function cargar(): Promise<void> {
    pedido ??= fetchPermisosBoleto()
      .then((p) => void (permisos.value = p))
      .catch(() => void (permisos.value = { anular: false, reasignar: false }))
    return pedido
  }

  const anular = computed(() => permisos.value?.anular ?? false)
  const reasignar = computed(() => permisos.value?.reasignar ?? false)

  return { permisos, anular, reasignar, cargar }
})
