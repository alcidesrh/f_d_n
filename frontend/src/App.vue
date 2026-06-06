<template>
	<div v-if="!schemaStore.isLoaded" class="fullscreen flex flex-center bg-neutral-1">
		<q-spinner size="3rem" />
	</div>
	<router-view v-else />
</template>

<script setup lang="ts">
	const schemaStore = useSchemaStore()
</script>
