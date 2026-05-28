<script setup lang="ts">
// const store = useUserStore();
const store = useCurrentStateStore()
</script>

<template>
	<div w-full>
		<collection :store="store" field="username" />
	</div>
</template>
