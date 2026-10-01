/**
 * Carrito: los asientos apartados en el backend al pulsar "Pagar asientos"
 * (ADR-023). El token vive en `sessionStorage`: sobrevive a la vuelta del
 * banco (3-D Secure) en la misma pestaña, y otra pestaña empieza su propia
 * compra.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'
import * as api from './api'
import type { Carrito, ViajePedido } from './tipos'

const CLAVE = 'fdn.carrito'

function leerToken(): string | null {
  try {
    return sessionStorage.getItem(CLAVE)
  } catch {
    return null
  }
}

function guardarToken(token: string | null) {
  try {
    if (token) sessionStorage.setItem(CLAVE, token)
    else sessionStorage.removeItem(CLAVE)
  } catch {
    // Sin almacenamiento el carrito dura lo que la pestaña.
  }
}

export const useCarrito = defineStore('carrito', () => {
  const token = ref<string | null>(leerToken())
  const carrito = shallowRef<Carrito | null>(null)
  const ocupado = ref(false)

  const viajes = computed(() => carrito.value?.viajes ?? [])
  const vacio = computed(() => viajes.value.length === 0)
  const asientos = computed(() => viajes.value.reduce((n, v) => n + v.asientos.length, 0))

  function aplicar(c: Carrito) {
    carrito.value = c
    token.value = c.token
    guardarToken(c.token)
  }

  async function refrescar() {
    if (!token.value) return
    try {
      aplicar(await api.verCarrito(token.value))
    } catch {
      olvidar()
    }
  }

  /** Aparta todo (ida y regreso) o nada: si otro tomó algún asiento, `ErrorPublico` 409 con los conflictos. */
  async function reservar(pedido: ViajePedido[]) {
    ocupado.value = true
    try {
      aplicar(await api.reservar(pedido, token.value))
    } finally {
      ocupado.value = false
    }
  }

  /** Suelta los asientos (al volver a elegir): otros pueden tomarlos. */
  async function vaciar() {
    if (token.value && !vacio.value) await api.vaciar(token.value).catch(() => undefined)
    if (carrito.value) carrito.value = { ...carrito.value, viajes: [], total: null, expira: null }
  }

  /** Tras comprar (o si venció): el próximo viaje usa otro token. */
  function olvidar() {
    carrito.value = null
    token.value = null
    guardarToken(null)
  }

  return { token, carrito, ocupado, viajes, vacio, asientos, refrescar, reservar, vaciar, olvidar }
})
