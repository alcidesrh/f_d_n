<template>
	<q-layout ref="layout" class="layout" id="layout" :class="layoutClass">
		<Notify />
		<Topbar />

		<!-- <header class="topbar">Topbar</header> -->
		<!-- <aside class="left-sidebar" @click="setLeft('mini')">Left Sidebar</aside> -->
		<SidebarLeft />

		<q-page-container class="layout-main-content">
			<q-page class="h-full u-p-xs lg:u-px-l m-auto relative">
				<!-- <div id="intersectionObservertarget" class="absolute top-78px w-20px h-20px bg-black" /> -->

				<RouterView v-slot="{ Component, route }">
					<transition :name="route.meta.transition || 'route'" mode="out-in">
						<component :is="Component" :key="route.name" />
					</transition>
				</RouterView>
			</q-page>
		</q-page-container>

		<SidebarRight />
	</q-layout>
</template>

<script lang="ts" setup>
	import { usePermission } from '@/composables/usePermission'
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { useSidebarStore } from '@/stores/autoimport/sidebar'

	const sidebarLeftStore = useSidebarStore('sidebarLeft', 'left')
	const sidebarRightStore = useSidebarStore('sidebarRight', 'right')
	const layoutClass = computed(() => `left-${sidebarLeftStore.mode} right-${sidebarRightStore.mode}`)
	const loadingStore = useLoadingStore()
	const session = useUserSessionStore()
	const { can } = usePermission()

	const observer = new IntersectionObserver(
		(e) => {
			cl(e)
			const el = document.querySelector('.topbar')
			if (e[0].intersectionRatio < 1) el.classList.add('layout-topbar-sticky')
			else el.classList.remove('layout-topbar-sticky')
		},
		{
			threshold: 1,
			// box-shadow: $shadow-3;
		},
	)

	onMounted(async () => {
		const el = document.querySelector('#intersectionObservertarget')
		observer.observe(el)
	})
	onUnmounted(() => observer.disconnect())
	// ejemplos

	// setLeft('mini')
	// setRight('close')

	// setLeft('large')
	// setRight('large')
</script>
