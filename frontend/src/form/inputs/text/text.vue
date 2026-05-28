<template>
	<q-input
		:for="context.id"
		outlined
		flat
		dense
		bg-color="white"
		:id="context.id"
		v-model="value"
		:placeholder="context.placeholder"
		@update:model-value="handleInput"
		class="w-full"
		:debounce="1000"
		stack-label
	/>
</template>
<script setup>
	const props = defineProps({
		context: Object,
	})
	const value = ref(props.context._value || '')

	watch(
		() => props.context._value,
		(v) => (value.value = v),
	)
	function handleInput(e) {
		props.context.node.input(e)
	}
	function reset() {
		value.value = ''
		props.context.node.input('')
	}
</script>
