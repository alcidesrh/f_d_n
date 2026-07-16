<template>
	<div class="collection-cell-wrap overflow-y-auto my-5px">
		<template v-if="column.name == 'createdAt' || column.name == 'updatedAt'" class="date">
			<span v-html="dformat(data[column.name])"> </span>
		</template>
		<span v-else-if="column.name == 'id'" :class="column.schema ? `highlight-${index}` : ''" :data-property="column.name">
			{{ getIdFromIri(data.id) }}
		</span>

		<span v-else-if="data[column.name]?.label">
			<q-chip dense size="sm" class="lowercase">
				{{ data[column.name]?.label }}
			</q-chip>
		</span>

		<span v-else-if="column.name == 'status'" class="capitalize">
			{{ data[column.name] }}
		</span>
		<div v-else-if="Array.isArray(data[column.name]?.collection)" class="flex flex-wrap gap-1">
			<q-chip size="sm" class="lowercase" dense v-for="v in data[column.name].collection" :key="v.id">
				{{ v.label }}
			</q-chip>
		</div>
		<div v-else-if="Array.isArray(data[column.name])" class="flex flex-wrap gap-1">
			<q-chip class="py-2 px-3 lowercase u--text-1! font-medium" dense size="sm" v-for="v in data[column.name]" :key="v.id">
				{{ v.label || v }}
			</q-chip>
		</div>
		<template v-else-if="column.name == 'icon'">
			<icon :name="data[column.name]" class="text-22px" />
		</template>
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
