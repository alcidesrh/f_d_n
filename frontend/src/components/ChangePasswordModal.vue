<template>
	<q-dialog v-model="visible" persistent>
		<q-card style="min-width: 400px">
			<q-card-section class="row items-center q-pb-none">
				<div class="text-h6">Cambiar Contraseña</div>
				<q-space />
				<q-btn flat round dense icon="sym_o_close" v-close-popup />
			</q-card-section>

			<q-card-section>
				<FormKit type="form" :actions="false" @submit="handleSubmit" autocomplete="off">
					<FormKit type="select" name="username" label="Usuario" :options="userOptions" placeholder="Seleccionar usuario" validation="required" :autocomplete="true" />
					<FormKit type="password" name="password" label="Nueva Contraseña" placeholder="Ingrese la nueva contraseña" validation="required" />
					<div class="flex justify-end q-mt-md">
						<q-btn flat label="Cancelar" color="negative" class="q-mr-sm" v-close-popup />
						<q-btn type="submit" label="Cambiar contraseña" color="primary" :loading="submitting" />
					</div>
				</FormKit>
			</q-card-section>
		</q-card>
	</q-dialog>
</template>

<script setup lang="ts">
	const props = defineProps<{ modelValue: boolean }>()
	const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

	const visible = computed({
		get: () => props.modelValue,
		set: (v) => emit('update:modelValue', v),
	})

	const submitting = ref(false)
	const userOptions = ref<{ id: string; label: string }[]>([])

	onMounted(async () => {
		try {
			const api = useApi()
			const users = await api.get('/users-brief')
			userOptions.value = users.map((u: any) => ({ id: u.username, label: u.label }))
		} catch {
			userOptions.value = []
		}
	})

	async function handleSubmit(formData: any) {
		if (!formData.username || !formData.password) return
		submitting.value = true
		try {
			alert(34)
			const api = useApi()
			await api.post('/change-password', {
				username: formData.username,
				password: formData.password,
			})
			visible.value = false
			useQuasar().notify({
				type: 'positive',
				message: 'Contraseña cambiada exitosamente',
			})
		} catch (err: any) {
			useQuasar().notify({
				type: 'negative',
				message: err?.message || err || 'Error al cambiar la contraseña',
			})
		} finally {
			submitting.value = false
		}
	}
</script>
