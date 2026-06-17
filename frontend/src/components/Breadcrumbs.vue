<template>
	<div class="flex text-surface-7 text-.90rem justify-center items-center capitalize bradcrumbs">
		<div v-for="(crumb, index) in breadcrumbs" :key="index" class="h-50px flex items-center">
			<icon v-if="crumb.icon" :name="crumb.icon" class="mr-5px text-.85rem text-surface-8" />
			<span v-if="crumb.to" @click="$router.push({ path: crumb.to })" class="cursor-pointer hover:text-surface-9">
				{{ crumb.label }}
			</span>
			<span v-else>{{ crumb.label }}</span>
			<icon v-if="index < breadcrumbs.length - 1" name="arrow_forward_ios" class="text-14px mx-5px text-surface-5 font-200" />
		</div>
	</div>
</template>

<script setup>
	const { breadcrumbs } = useBreadcrumbs()
</script>
