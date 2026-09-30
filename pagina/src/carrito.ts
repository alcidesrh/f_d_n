/**
 * Carrito de asientos (reservas del backend). El token vive en
 * `sessionStorage`: sobrevive a la vuelta del banco (3-D Secure) en la misma
 * pestaña, y otra pestaña empieza su propia compra.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'
import * as api from './api'
import type { Carrito } from './tipos'

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

  const asientos = computed(() => carrito.value?.asientos ?? [])
  const vacio = computed(() => asientos.value.length === 0)

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

  /** Aparta o suelta un asiento. Si el carrito es de otro viaje, lo vacía primero. */
  async function alternar(salida: number, trayecto: number, asiento: number) {
    ocupado.value = true
    try {
      if (asientos.value.some((a) => a.asiento === asiento) && token.value) {
        aplicar(await api.liberar(token.value, asiento))
        return
      }
      const otroViaje =
        !vacio.value &&
        (carrito.value?.salida?.id !== salida || carrito.value?.trayecto?.id !== trayecto)
      if (otroViaje && token.value) {
        await api.vaciar(token.value)
        olvidar()
      }
      aplicar(await api.apartar({ salida, trayecto, asiento, token: token.value }))
    } finally {
      ocupado.value = false
    }
  }

  async function vaciar() {
    if (token.value) await api.vaciar(token.value).catch(() => undefined)
    olvidar()
  }

  /** Tras comprar (o si venció): el próximo viaje usa otro token. */
  function olvidar() {
    carrito.value = null
    token.value = null
    guardarToken(null)
  }

  return { token, carrito, ocupado, asientos, vacio, refrescar, alternar, vaciar, olvidar }
})
