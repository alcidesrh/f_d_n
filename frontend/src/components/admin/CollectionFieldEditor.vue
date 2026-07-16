<template>
	<draggable-fields v-model="localFields" type="list">
		<template #content="{ element }">
			<q-checkbox v-model="element.sortable" label="Sortable" />
			<q-checkbox v-model="element.filterable" label="Filterable" />
		</template>
	</draggable-fields>
</template>

<script setup lang="ts">
const localFields = defineModel()
</script>
