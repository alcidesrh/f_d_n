<script setup>
	import { useResponsive } from '@/composables/useBreakpoints'
	const responsive = useResponsive()
</script>
<template>
	<q-layout view="lHh Lpr lFf">
		<q-drawer v-model="drawer" :overlay="sidebarBehavior === 'overlay'" :mini="sidebarBehavior === 'mini'">
			<slot name="menu">
				<div class="auto-grid" :class="'auto-grid-' gridColumns">
					<q-card class="my-card" v-for="item in 20">
						<img src="https://cdn.quasar.dev/img/mountains.jpg" />
						<q-card-section>
							<div class="text-h6">Our Changing Planet</div>
							<div class="text-subtitle2">by John Doe</div>
						</q-card-section>
						<q-card-section> Lorem ipsum dolor sit amet, consectetur adipiscing elit </q-card-section>
					</q-card>
				</div>
			</slot>
		</q-drawer>

		<q-page-container>
			<slot />
		</q-page-container>
	</q-layout>
</template>
