/**
 * Sesión del usuario: identidad y token (persistidos). Login y logout viven
 * aquí para que las pantallas solo llamen a la acción. Lo que puede hacer el
 * usuario no va aquí: lo trae `core/acceso` (`GET /api/me/acceso`, ADR-039).
 *
 * Suplantación: un administrador del sistema entra como otro usuario con un
 * token aparte (`POST /usuarios/{id}/suplantar`); su propia sesión queda en
 * `original` para volver (`terminarSuplantacion`, o sola si el token vence).
 * Al cambiar de identidad la app se recarga: así no queda nada en memoria
 * (stores, chat, Mercure) del usuario anterior.
 */
import { defineStore } from 'pinia'
import { computed, nextTick, ref } from 'vue'
import { request } from '@/core/http'
import { entrarComo } from './alcanceSesion'

/** Lo que debe salir antes de perder el token (p. ej. la bitácora de actividad pendiente). */
const antesDeSalir = new Set<() => Promise<unknown> | void>()

/** Registra una tarea que corre antes del logout; devuelve cómo quitarla. */
export function antesDeCerrarSesion(tarea: () => Promise<unknown> | void): () => void {
  antesDeSalir.add(tarea)
  return () => antesDeSalir.delete(tarea)
}

interface LoginResponse {
  username: string
  token: string
}

/** La sesión propia de quien suplanta, para volver a ella. */
export interface SesionGuardada {
  user: string
  token: string
}

/** Recarga la app en `destino`: lo de la identidad anterior no queda en memoria. */
let recargar = (destino: string) => window.location.assign(destino)

/** @internal pruebas */
export function cambiarRecarga(f: (destino: string) => void): void {
  recargar = f
}

export const useSessionStore = defineStore(
  'session',
  () => {
    const user = ref<string | null>(null)
    const token = ref<string | null>(null)
    const original = ref<SesionGuardada | null>(null)
    const isAuthenticated = computed(() => Boolean(user.value && token.value))
    const suplantando = computed(() => original.value !== null)

    /** Lanza `HttpError` (401 = credenciales inválidas) para que el formulario lo muestre. */
    async function login(credentials: { username: string; password: string }) {
      const response = await request<LoginResponse>('/login', {
        method: 'POST',
        body: credentials,
        loadingKey: 'login',
        skipUnauthorized: true,
      })
      user.value = response.username
      token.value = response.token
      entrarComo(response.username)
    }

    async function logout() {
      await Promise.allSettled([...antesDeSalir].map((tarea) => tarea()))
      // Suplantando, cierra primero la suplantación y luego la sesión propia.
      if (original.value) {
        await cerrarSuplantacion()
        volverAOriginal()
      }
      try {
        await request('/logout', { method: 'POST', skipUnauthorized: true })
      } finally {
        clear()
      }
    }

    /** Entra como el usuario `id`. Lanza `HttpError` (403/422: no se puede, con el motivo). */
    async function suplantar(id: number | string, motivo?: string | null) {
      const response = await request<LoginResponse>(`/usuarios/${id}/suplantar`, {
        method: 'POST',
        body: { motivo: motivo ?? null },
        loadingKey: 'suplantar',
      })
      await Promise.allSettled([...antesDeSalir].map((tarea) => tarea()))
      if (!original.value && user.value && token.value) original.value = { user: user.value, token: token.value }
      user.value = response.username
      token.value = response.token
      entrarComo(response.username)
      await nextTick()
      recargar('/')
    }

    /** Vuelve a la cuenta propia (revoca el token de suplantación). */
    async function terminarSuplantacion(destino = '/') {
      if (!original.value) return
      await Promise.allSettled([...antesDeSalir].map((tarea) => tarea()))
      await cerrarSuplantacion()
      volverAOriginal()
      await nextTick()
      recargar(destino)
    }

    /**
     * El token de suplantación ya no sirve (venció o lo cerraron): vuelve a la
     * cuenta propia sin llamar a la API. Devuelve si había a dónde volver.
     */
    function recuperarOriginal(): boolean {
      if (!original.value) return false
      volverAOriginal()
      return true
    }

    async function cerrarSuplantacion() {
      try {
        await request('/suplantacion/salir', { method: 'POST', skipUnauthorized: true })
      } catch {
        // Vencido o ya cerrado: igual se vuelve a la cuenta propia.
      }
    }

    function volverAOriginal() {
      const propia = original.value
      if (!propia) return
      original.value = null
      user.value = propia.user
      token.value = propia.token
      entrarComo(propia.user)
    }

    function clear() {
      user.value = null
      token.value = null
      original.value = null
    }

    return {
      user,
      token,
      original,
      isAuthenticated,
      suplantando,
      login,
      logout,
      suplantar,
      terminarSuplantacion,
      recuperarOriginal,
      clear,
    }
  },
  { global: true, persist: { pick: ['user', 'token', 'original'] } },
)
