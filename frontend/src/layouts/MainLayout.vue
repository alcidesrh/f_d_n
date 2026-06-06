<template>
	<q-layout view="hHh LpR fFf">
		<Notify />
		<Topbar />
		<SidebarDrawer store-id="sidebarLeft" position="left" v-once>
			<template #content="{ data }">
				<nav>
					<!-- <ResponsiveComponent
            :mobile="{
              cmp: 'MenuMini',
              props: { items: customize },
            }"
            :desktop="{
              cmp: 'MenuLarge',
              props: { store: data, menu: customize },
            }"
          /> -->
					<MenuLarge v-if="data.mode == data.modeStates.large" :store="data" :menu="customize" />

					<MenuMini v-else-if="data.mode == data.modeStates.mini" :items="customize"> </MenuMini>
				</nav>
			</template>
		</SidebarDrawer>
		<q-page-container class="main-content h-[100vh] relative" :class="[sidebarStore.position, mode]">
			<q-page class="h-full u-p-xs lg:u-px-l m-auto relative">
				<!-- <Suspense> -->
				<RouterView v-slot="{ Component, route }">
					<transition :name="route.meta.transition || 'route'" mode="out-in">
						<component :is="Component" :key="route.name" />
					</transition>
				</RouterView>
				<!-- </Suspense> -->
			</q-page>
		</q-page-container>

		<q-inner-loading :showing="loadingStore.loading">
			<!-- <q-spinner-gears size="50px" color="primary" :thickness="2" /> -->
			<q-spinner-dots color="primary" size="3em" />
		</q-inner-loading>
	</q-layout>
</template>

<script lang="ts" setup>
	import { useSidebarStore } from '@/stores/autoimport/sidebar'
	import { ref } from 'vue'
	const sidebarStore = useSidebarStore('sidebarLeft', 'left')
	const loadingStore = useLoadingStore()

	const { mode, modeStates } = storeToRefs(sidebarStore)

	const menu = [
		{
			label: 'Limpiar cache',
			icon: 'cached',
			name: 'refresh',
			type: 'action',
			command: () => {
				fdn.value.refresh()
			},
		},
		{
			label: 'Mi cuenta',
			icon: 'account_circle',
			open: true,
			children: [
				{
					label: 'Editar',
					icon: 'person_edit',
					name: 'account_edit',
					params: { id: 'user.value.username' },
				},
				{
					label: 'Chequear',
					icon: 'transit_ticket',
					to: '',
				},
				{
					label: 'Buscar',
					icon: 'search',
					to: '',
				},
				{
					label: 'Estadísticas',
					icon: 'graph_7',
					to: '',
				},
			],
		},
	]
	const menuStore = useMenuStateStore('menu-left', menu)
	const { toggle } = storeToRefs(menuStore)

	const leftDrawerOpen = ref(false)
	const rightDrawerOpen = ref(false)

	const customize = ref([
		{
			label: 'Menu',
			icon: 'menu',
			name: 'collectionMenus',
		},
		{
			label: 'Mi cuenta',
			icon: 'account_circle',
			open: true,
			children: [
				{
					label: 'Editar',
					icon: 'person_edit',
					name: 'account_edit',
					params: '{ id: user.value.username }',
				},
				{
					label: 'Chequear',
					icon: 'transit_ticket',
					to: '',
					children: [
						{
							label: 'Editar dsaf dsf dsf dsf ds',
							icon: 'person_edit',
							name: 'account_edit',
							params: '{ id: user.value.username }',
						},
						{
							label: 'Chequear',
							icon: 'transit_ticket',
							to: '',
						},
						{
							label: 'Buscar',
							icon: 'search',
							to: '',
							children: [
								{
									label: 'Editar',
									icon: 'person_edit',
									name: 'account_edit',
									params: '{ id: user.value.username }',
								},
								{
									label: 'Chequear',
									icon: 'transit_ticket',
									to: '',
								},
								{
									label: 'Buscar',
									icon: 'search',
									to: '',
								},
							],
						},
					],
				},
				{
					label: 'Buscar',
					icon: 'search',
					to: '',
				},
				{
					label: 'Estadísticas',
					icon: 'graph_7',
					to: '',
				},
			],
		},
	])
</script>
