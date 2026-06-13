<script setup>
	const props = defineProps({
		context: Object,
	})
</script>

<template>
	<q-btn color="primary" v-bind="context.attrs.binds" :loading="context?.loading" />
</template>
