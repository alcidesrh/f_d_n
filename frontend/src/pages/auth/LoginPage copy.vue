<template>
	<q-layout view="hHh LpR fFf" class="bg-grey-2">
		<q-page-container>
			<q-page class="flex flex-center">
				<q-card class="q-pa-lg shadow-2" style="width: 400px; max-width: 90vw">
					<q-card-section class="text-center">
						<div class="text-h5 q-mb-md">Iniciar Sesión</div>
						<div class="text-caption text-grey">Transporte Fuentes del Norte</div>
					</q-card-section>

					<q-card-section>
						<q-form @submit="handleLogin" class="q-gutter-md">
							<q-input v-model="username" label="Usuario" outlined autocomplete="username" :rules="[(val) => !!val || 'El usuario es requerido']" />

							<q-input v-model="password" label="Contraseña" outlined type="password" autocomplete="current-password" :rules="[(val) => !!val || 'La contraseña es requerida']" />

							<div v-if="error" class="text-negative text-center q-mb-sm">
								{{ error }}
							</div>

							<q-btn type="submit" label="Ingresar" color="primary" class="full-width" :loading="isLoading" />
						</q-form>
					</q-card-section>
				</q-card>
			</q-page>
		</q-page-container>
	</q-layout>
</template>

<script lang="ts" setup>
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { ref } from 'vue'
	import { useRouter } from 'vue-router'

	const router = useRouter()
	const session = useUserSessionStore()

	const username = ref('')
	const password = ref('')
	const isLoading = ref(false)
	const error = ref('')

	async function handleLogin() {
		isLoading.value = true
		error.value = ''

		try {
			const response = await fetch('/login', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					username: username.value,
					password: password.value,
				}),
			})

			if (!response.ok) {
				throw new Error('Credenciales inválidas')
			}

			await session.fetchPermissions()

			const redirect = session.redirectTo || '/'
			session.redirectTo = '/'
			router.push(redirect)
		} catch (e: any) {
			error.value = e.message || 'Error al iniciar sesión'
		} finally {
			isLoading.value = false
		}
	}
</script>
