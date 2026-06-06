<template>
	<div class="collection-cell-wrap">
		<div v-if="column.name == 'createdAt' || column.name == 'updatedAt'" class="date" v-html="dformat(data[column.name])"></div>
		<span v-else-if="column.name == 'id'" :class="column.schema ? `highlight-${index}` : ''" :data-property="column.name">
			{{ getIdFromIri(data.id) }}
		</span>

		<span v-else-if="data[column.name]?.label">
			<q-chip dense size="md">
				{{ data[column.name]?.label }}
			</q-chip>
		</span>

		<span v-else-if="column.name == 'status'" class="capitalize">
			{{ data[column.name] }}
		</span>
		<div v-else-if="Array.isArray(data[column.name]?.collection)" class="flex flex-wrap gap-1">
			<q-chip class="p-3!" dense size="md" v-for="v in data[column.name].collection" :key="v.id">
				{{ v.label }}
			</q-chip>
		</div>
		<div v-else-if="Array.isArray(data[column.name])" class="flex flex-wrap gap-1">
			<q-chip class="py-2 px-3 u--text-1! font-semibold" dense size="md" v-for="v in data[column.name]" :key="v.id">
				{{ v.label }}
			</q-chip>
		</div>
		<div v-else class="overflow-hidden text-ellipsis" :class="[column.schema ? `highlight-${index}` : '']" :data-property="column.name">
			{{ data[column.name] }}
		</div>
	</div>
</template>
<script setup lang="ts">
	const props = defineProps<{
		data: any
		column: any
		index: any
	}>()
</script>
