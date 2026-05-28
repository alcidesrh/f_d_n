<template>
	<q-tr :props="data">
		<q-td v-for="(c, i) in data.cols" :key="c.field" :props="data" class="text-left! col-wraper">
			<div :class="[c?.class]">
				<slot :name="c.name" :data="data[c.name]">
					<collection-cell v-if="!c.action" :data="data.row" :column="c" :index="i" />
				</slot>
			</div>
		</q-td>
		<q-td v-if="!selectionMode">
			<slot name="actions_row"></slot>
		</q-td>
		<q-td v-else class="text-center">
			<q-checkbox v-model="data.selected" color="secondary" class="m-auto" />
		</q-td>
	</q-tr>
</template>
<script setup lang="ts">
	interface Props {
		data: any
		selectionMode: any
	}
	cl.info(data)
	const { data, selectionMode } = defineProps<Props>()
	const selected = ref([])
</script>
