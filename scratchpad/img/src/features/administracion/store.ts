/**
 * Cajas de `/administracion` desde el servidor (`core/administracion/api`).
 * Fuera de la configuración, las que ve el usuario; al configurar, todas
 * (ocultas y restringidas incluidas) con su acceso. Cada operación aplica la
 * configuración que responde el servidor; ordenar se muestra al instante y
 * se deshace si el servidor lo rechaza.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import * as api from '@/core/administracion/api'
import type { Administracion, EnlacePayload, Seccion, SeccionPayload } from '@/core/administracion/api'
import { ordenDe } from '@/core/administracion/disposicion'
import { notify } from '@/core/notify'

export const useAdministracionStore = defineStore('administracion', () => {
  const secciones = ref<Seccion[]>([])
  const puedeConfigurar = ref(false)
  const configurando = ref(false)
  const cargando = ref(false)
  const error = ref('')

  /**
   * Cada lectura o escritura toma un turno y solo se aplica la respuesta del
   * último: una carga lenta que llega después de guardar no pisa lo guardado.
   */
  let turno = 0
  function aplicar(r: Administracion, suyo: number) {
    if (suyo !== turno) return
    secciones.value = r.secciones
    puedeConfigurar.value = r.puedeConfigurar
  }

  async function cargar() {
    const suyo = ++turno
    cargando.value = true
    error.value = ''
    try {
      aplicar(await api.fetchAdministracion(configurando.value), suyo)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'No se pudo cargar la administración.'
    } finally {
      cargando.value = false
    }
  }

  async function configurar(si: boolean) {
    configurando.value = si
    await cargar()
  }

  /** Corre una escritura; `true` si se guardó (el error ya se avisó). */
  async function escribir(operacion: () => Promise<Administracion>): Promise<boolean> {
    const suyo = ++turno
    try {
      aplicar(await operacion(), suyo)
      return true
    } catch (e) {
      notify.error(e instanceof Error ? e.message : 'No se pudo guardar.')
      return false
    }
  }

  /** Nuevo orden (cajas, enlaces y en qué caja va cada uno): se ve al instante. */
  async function ordenar(nuevas: Seccion[]) {
    const antes = secciones.value
    secciones.value = nuevas
    if (!(await escribir(() => api.guardarOrden(ordenDe(nuevas))))) {
      secciones.value = antes
      await cargar()
    }
  }

  return {
    secciones,
    puedeConfigurar,
    configurando,
    cargando,
    error,
    cargar,
    configurar,
    ordenar,
    crearSeccion: (p: SeccionPayload) => escribir(() => api.crearSeccion(p)),
    guardarSeccion: (id: number, p: SeccionPayload) => escribir(() => api.guardarSeccion(id, p)),
    eliminarSeccion: (id: number) => escribir(() => api.eliminarSeccion(id)),
    crearEnlace: (seccionId: number, p: EnlacePayload) => escribir(() => api.crearEnlace(seccionId, p)),
    guardarEnlace: (id: number, p: EnlacePayload) => escribir(() => api.guardarEnlace(id, p)),
    eliminarEnlace: (id: number) => escribir(() => api.eliminarEnlace(id)),
  }
})
