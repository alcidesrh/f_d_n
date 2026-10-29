/**
 * Sesión del usuario: identidad y token (persistidos). Login y logout viven
 * aquí para que las pantallas solo llamen a la acción. Lo que puede hacer el
 * usuario no va aquí: lo trae `core/acceso` (`GET /api/me/acceso`, ADR-039).
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { request } from '@/core/http'
import { entrarComo } from './alcanceSesion'

interface LoginResponse {
  username: string
  token: string
}

export const useSessionStore = defineStore(
  'session',
  () => {
    const user = ref<string | null>(null)
    const token = ref<string | null>(null)
    const isAuthenticated = computed(() => Boolean(user.value && token.value))

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
      try {
        await request('/logout', { method: 'POST', skipUnauthorized: true })
      } finally {
        clear()
      }
    }

    function clear() {
      user.value = null
      token.value = null
    }

    return { user, token, isAuthenticated, login, logout, clear }
  },
  { global: true, persist: { pick: ['user', 'token'] } },
)
