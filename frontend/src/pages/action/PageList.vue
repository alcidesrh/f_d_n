<template>
	<q-page>
		<div class="q-pa-md">
			<div class="text-h5 q-mb-md">Acciones</div>
			<q-table
				:rows="actions"
				:columns="columns"
				row-key="id"
				:loading="loading"
			>
				<template v-slot:top>
					<q-btn
						v-if="can('admin.accion.crear')"
						color="primary"
						label="Nueva Acción"
						@click="$router.push({ name: 'ActionCreate' })"
					/>
				</template>
				<template v-slot:body-cell-actions="props">
					<q-td :props="props">
						<q-btn
							v-if="can('admin.accion.ver')"
							flat dense icon="visibility"
							@click="$router.push({ name: 'ActionShow', params: { id: props.row.id } })"
						/>
						<q-btn
							v-if="can('admin.accion.editar')"
							flat dense icon="edit"
							@click="$router.push({ name: 'ActionUpdate', params: { id: props.row.id } })"
						/>
					</q-td>
				</template>
			</q-table>
		</div>
	</q-page>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { listActions } from 'src/stores/action/list'
import { usePermission } from 'src/composables/usePermission'

const { can } = usePermission()

const actions = ref([])
const loading = ref(false)

const columns = [
	{ name: 'codigo', label: 'Código', field: 'codigo', sortable: true },
	{ name: 'nombre', label: 'Nombre', field: 'nombre', sortable: true },
	{ name: 'recurso', label: 'Recurso', field: 'recurso', sortable: true },
	{ name: 'operacion', label: 'Operación', field: 'operacion', sortable: true },
	{ name: 'grupo', label: 'Grupo', field: 'grupo', sortable: true },
	{ name: 'actions', label: 'Acciones', field: 'actions' },
]

onMounted(async () => {
	loading.value = true
	try {
		actions.value = await listActions()
	} finally {
		loading.value = false
	}
})
</script>
