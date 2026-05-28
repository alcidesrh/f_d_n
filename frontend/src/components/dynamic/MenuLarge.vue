<template>
	<q-tree :nodes="menu" node-key="label" default-expand-all class="text-surface-4 mt-20px">
		<template v-slot:default-header="prop">
			<div class="row items-center cursor-pointer">
				<icon :name="prop.node.icon || 'sym_o_star'" class="mr-10px" />
				<div class="text-color">{{ prop.node.label }}</div>
			</div>
		</template>
	</q-tree>
</template>

<script setup lang="ts">
	const props = defineProps({
		menu: {
			type: Array<any>,
			default: null,
		},
		store: {
			type: Object,
			default: {},
		},
	})
</script>
<style scoped></style>
`
