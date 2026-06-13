<template>
	<q-page class="q-pa-md">
		<div class="text-h5 q-mb-md">Detalle de Acción</div>
		<q-card v-if="action" class="q-pa-md">
			<q-list>
				<q-item>
					<q-item-section>
						<q-item-label caption>Código</q-item-label>
						<q-item-label>{{ action.codigo }}</q-item-label>
					</q-item-section>
				</q-item>
				<q-item>
					<q-item-section>
						<q-item-label caption>Nombre</q-item-label>
						<q-item-label>{{ action.nombre }}</q-item-label>
					</q-item-section>
				</q-item>
				<q-item>
					<q-item-section>
						<q-item-label caption>Recurso</q-item-label>
						<q-item-label>{{ action.recurso }}</q-item-label>
					</q-item-section>
				</q-item>
				<q-item>
					<q-item-section>
						<q-item-label caption>Operación</q-item-label>
						<q-item-label>{{ action.operacion }}</q-item-label>
					</q-item-section>
				</q-item>
				<q-item v-if="action.grupo">
					<q-item-section>
						<q-item-label caption>Grupo</q-item-label>
						<q-item-label>{{ action.grupo }}</q-item-label>
					</q-item-section>
				</q-item>
			</q-list>
		</q-card>
	</q-page>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showAction } from 'src/stores/action/show'

const route = useRoute()
const action = ref<any>(null)

onMounted(async () => {
	const id = Number(route.params.id)
	action.value = await showAction(id)
})
</script>
