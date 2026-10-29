/**
 * Lo que puede hacer el usuario con sesión (`GET /api/me/acceso`, ADR-039):
 * el router, los menús y las pantallas lo consultan para mostrar u ocultar.
 * Se carga al entrar, se refresca al volver a la pestaña y cuando el backend
 * avisa por Mercure que cambiaron sus asignaciones (`tipo: 'acceso'` en el
 * tópico privado del chat). Quien autoriza es siempre la API.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http, request } from '@/core/http'
import { debeElegirEstacion, puedeEntrar, puedeRecurso, tiene } from './reglas'
import type { Exige, MiAcceso } from './tipos'

export const useAccesoStore = defineStore('acceso', () => {
  const acceso = ref<MiAcceso | null>(null)
  /** El selector de estación de trabajo, abierto a pedido (cabecera, Mi cuenta). */
  const eligiendoEstacion = ref(false)
  let pendiente: Promise<MiAcceso | null> | null = null
  /** Se llama cuando cambia lo que puede (los menús se recargan). */
  const alCambiar = new Set<() => void>()

  async function cargar(): Promise<MiAcceso | null> {
    pendiente ??= http
      .get<MiAcceso>('/me/acceso', { silent: true })
      .then((datos) => {
        const cambio = acceso.value !== null && acceso.value.huella !== datos.huella
        acceso.value = datos
        if (cambio) alCambiar.forEach((f) => f())
        return datos
      })
      .catch(() => acceso.value)
      .finally(() => {
        pendiente = null
      })
    return pendiente
  }

  /** Lo cargado, o lo carga si todavía no (el guard del router lo espera). */
  async function asegurar(): Promise<MiAcceso | null> {
    return acceso.value ?? (await cargar())
  }

  async function elegirEstacion(id: number): Promise<void> {
    acceso.value = await request<MiAcceso>('/me/estacion-trabajo', { method: 'PUT', body: { estacion: id } })
    alCambiar.forEach((f) => f())
  }

  function limpiar() {
    acceso.value = null
    eligiendoEstacion.value = false
  }

  function suscribir(f: () => void): () => void {
    alCambiar.add(f)
    return () => alCambiar.delete(f)
  }

  return {
    acceso,
    eligiendoEstacion,
    cargado: computed(() => acceso.value !== null),
    sistema: computed(() => acceso.value?.sistema ?? false),
    estacionTrabajo: computed(() => acceso.value?.estacionTrabajo ?? null),
    estaciones: computed(() => acceso.value?.estaciones ?? []),
    debeElegirEstacion: computed(() => debeElegirEstacion(acceso.value)),
    puede: (exige: Exige | undefined) => tiene(acceso.value, exige),
    puedeRecurso: (entidad: string, operacion: 'ver' | 'editar' | 'eliminar') => puedeRecurso(acceso.value, entidad, operacion),
    puedeEntrar: (ruta: Parameters<typeof puedeEntrar>[1]) => puedeEntrar(acceso.value, ruta),
    cargar,
    asegurar,
    elegirEstacion,
    limpiar,
    suscribir,
  }
})
