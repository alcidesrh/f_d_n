<template>
	<div class="h-fit w-full flex justify-end gap-5 crud-button-wraper">
		<slot name="CrudSubmitButton">
			<q-btn-dropdown split outline class="bg-surface-1" label="Guardar" icon="sym_o_save" type="submit">
				<q-list class="crud-button-wraper">
					<q-item clickable v-close-popup @click="$emit('submit', 'reset')">
						<q-item-section side>
							<icon name="library_add" />
						</q-item-section>
						<q-item-section>
							<q-item-label>Guardar y agregar otro</q-item-label>
						</q-item-section>
					</q-item>

					<q-item clickable v-close-popup @click="$emit('cancel')">
						<q-item-section side>
							<icon name="list" />
						</q-item-section>
						<q-item-section>
							<q-item-label>Volver al listado</q-item-label>
						</q-item-section>
					</q-item>
					<q-item clickable v-close-popup @click="$emit('delete')">
						<q-item-section side>
							<icon name="delete" />
						</q-item-section>
						<q-item-section>
							<q-item-label>Eliminar</q-item-label>
						</q-item-section>
					</q-item>
				</q-list>
			</q-btn-dropdown>
		</slot>
	</div>
</template>

<script setup lang="ts">
	const { edit = false } = defineProps<{ edit: boolean }>()

	const emit = defineEmits(['cancel', 'delete', 'submit'])

	function submit() {
		emit('submit')
	}
</script>
