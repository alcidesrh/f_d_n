/**
 * Sesión del usuario: identidad, token y permisos (persistidos). Login y
 * logout viven aquí para que las pantallas solo llamen a la acción.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { request } from '@/core/http'

interface LoginResponse {
  username: string
  token: string
  permissions?: string[]
}

export const useSessionStore = defineStore(
  'session',
  () => {
    const user = ref<string | null>(null)
    const token = ref<string | null>(null)
    const permissions = ref<string[]>([])
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
      permissions.value = response.permissions ?? []
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
      permissions.value = []
    }

    return { user, token, permissions, isAuthenticated, login, logout, clear }
  },
  { persist: { pick: ['user', 'token', 'permissions'] } },
)
