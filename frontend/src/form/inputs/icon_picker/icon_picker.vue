<template>
	<IconPicker
		:model-value="context._value"
		@update:model-value="handleInput"
	/>
</template>

<script setup>
	const props = defineProps({
		context: Object,
	})

	function handleInput(val) {
		props.context.node.input(val)
	}
</script>
