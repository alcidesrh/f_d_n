import { defineStore } from 'pinia'
import type { SubmissionErrors } from '~/types/error'
import type { User } from '~/types/user'

interface State {
	user: User | null
	permissions: string[]
	isAuthenticated: ComputedRef<boolean>
	error?: string
	violations?: SubmissionErrors
	redirectTo: string
}

export const useUserSessionStore = defineStore(
	'userSession',
	() => {
		const user = ref<User | null | undefined>()
		const token = ref<string | null>(null)
		const permissions = ref<string[]>([])
		const error = ref<string | undefined>(undefined)
		const violations = ref<SubmissionErrors | undefined>(undefined)
		const redirectTo = ref('/')
		const isAuthenticated = computed(() => user.value && token.value)
		const isAdmin = computed(() =>
			permissions.value.some((p) => p.startsWith('admin.') || p === 'ROLE_ADMIN'),
		)

		function can(code: string): boolean {
			if (!permissions.value) return false
			return permissions.value.includes(code)
		}

		function canAny(codes: string[]): boolean {
			if (!permissions.value) return false
			return codes.some((c) => permissions.value.includes(c))
		}

		function canAll(codes: string[]): boolean {
			if (!permissions.value) return false
			return codes.every((c) => permissions.value.includes(c))
		}

		async function login(username: string, password: string) {
			error.value = undefined
			try {
				const response = await fetch('/login', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ username, password }),
				})

				if (!response.ok) {
					throw new Error('Credenciales inválidas')
				}

				await fetchSession()
			} catch (e: any) {
				error.value = e.message || 'Error al iniciar sesión'
				throw e
			}
		}

		async function fetchSession() {
			try {
				const restApi = await useApi()
				const response = await restApi.get('/me/permissions')
				permissions.value = response.actions || []
			} catch {
				permissions.value = []
			}
		}

		async function fetchPermissions() {
			try {
				const restApi = await useApi()
				const response = await restApi.get('/me/permissions')
				permissions.value = response.actions || []
			} catch {}
		}

		function setUser(u: User) {
			user.value = u
		}

		function clear() {
			token.value = null
			user.value = null
			permissions.value = []
			error.value = undefined
			violations.value = undefined
			redirectTo.value = '/'
		}

		function setError(e: string | undefined) {
			error.value = e
		}

		function setViolations(v: SubmissionErrors | undefined) {
			violations.value = v
		}

		return {
			user,
			token,
			permissions,
			isAuthenticated,
			isAdmin,
			error,
			violations,
			redirectTo,
			can,
			canAny,
			canAll,
			login,
			fetchSession,
			fetchPermissions,
			setUser,
			clear,
			setError,
			setViolations,
		}
	},
	{
		persist: {
			pick: ['user', 'token', 'permissions'],
		},
	},
)
