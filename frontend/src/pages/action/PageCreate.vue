<template>
	<q-page class="q-pa-md">
		<div class="text-h5 q-mb-md">Crear Acción</div>
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { createAction } from 'src/stores/action/create'

const router = useRouter()
const form = ref({
	codigo: '',
	nombre: '',
	recurso: '',
	operacion: '',
	grupo: '',
	ruta: '',
})

async function save() {
	try {
		await createAction(form.value)
		router.push({ name: 'ActionList' })
	} catch (e: any) {
		console.error(e)
	}
}
</script>
