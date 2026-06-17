<template>
	<q-checkbox
		:for="context.id"
		v-model="value"
		:label="context.label"
		@update:model-value="handleInput"
		dense
		class="w-full"
	/>
</template>
<script setup>
	import { ref, watch } from 'vue'

	const props = defineProps({
		context: Object,
	})
	
	const value = ref(props.context._value || false)

	watch(
		() => props.context._value,
		(v) => {
			value.value = !!v
		},
	)
	
	function handleInput(e) {
		props.context.node.input(e)
	}
</script>
