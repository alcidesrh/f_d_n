<template>
	<q-page class="q-pa-md">
		<div class="text-h5 q-mb-md">Editar Acción</div>
		<q-form @submit="save" class="q-gutter-md" style="max-width: 500px">
			<q-input v-model="form.codigo" label="Código" outlined :rules="[val => !!val || 'Requerido']" />
			<q-input v-model="form.nombre" label="Nombre" outlined :rules="[val => !!val || 'Requerido']" />
			<q-input v-model="form.recurso" label="Recurso" outlined :rules="[val => !!val || 'Requerido']" />
			<q-input v-model="form.operacion" label="Operación" outlined :rules="[val => !!val || 'Requerido']" />
			<q-input v-model="form.grupo" label="Grupo" outlined />
			<q-input v-model="form.ruta" label="Ruta" outlined />
			<q-btn type="submit" label="Guardar" color="primary" />
		</q-form>
	</q-page>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showAction } from 'src/stores/action/show'
import { updateAction } from 'src/stores/action/update'

const route = useRoute()
const router = useRouter()
const form = ref({
	codigo: '',
	nombre: '',
	recurso: '',
	operacion: '',
	grupo: '',
	ruta: '',
})

onMounted(async () => {
	const id = Number(route.params.id)
	const data = await showAction(id)
	form.value = { ...data }
})

async function save() {
	try {
		const id = Number(route.params.id)
		await updateAction(id, form.value)
		router.push({ name: 'ActionList' })
	} catch (e: any) {
		console.error(e)
	}
}
</script>
