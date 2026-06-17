<template>
	<q-layout view="hHh LpR fFf">
		<Notify />
		<Topbar />
		<SidebarDrawer store-id="sidebarLeft" position="left" v-once>
			<template #content="{ data }">
				<nav>
					<MenuLarge v-if="data.mode == data.modeStates.large" :store="data" :menu="filteredMenu" />
					<MenuMini v-else-if="data.mode == data.modeStates.mini" :items="filteredMenu"> </MenuMini>
				</nav>
			</template>
		</SidebarDrawer>
		<q-page-container class="main-content h-[100vh] relative" :class="[sidebarStore.position, mode]">
			<q-page class="h-full u-p-xs lg:u-px-l m-auto relative">
				<RouterView v-slot="{ Component, route }">
					<transition :name="route.meta.transition || 'route'" mode="out-in">
						<component :is="Component" :key="route.name" />
					</transition>
				</RouterView>
			</q-page>
		</q-page-container>

		<q-inner-loading :showing="loadingStore.loading">
			<q-spinner-dots color="primary" size="3em" />
		</q-inner-loading>
	</q-layout>
</template>

<script lang="ts" setup>
	import { usePermission } from '@/composables/usePermission'
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { useSidebarStore } from '@/stores/autoimport/sidebar'
	import { computed, ref } from 'vue'

	const sidebarStore = useSidebarStore('sidebarLeft', 'left')
	const loadingStore = useLoadingStore()
	const session = useUserSessionStore()
	const { can } = usePermission()

	const { mode, modeStates } = storeToRefs(sidebarStore)

	const menu = [
		// {
		// 	label: 'Limpiar cache',
		// 	icon: 'cached',
		// 	name: 'refresh',
		// 	type: 'action',
		// 	command: () => {
		// 		fdn.value.refresh()
		// 	},
		// },
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
					label: 'Cerrar sesión',
					icon: 'logout',
					type: 'action',
					command: () => {
						session.clear()
						window.location.href = '/login'
					},
				},
			],
		},
	]

	const adminMenu = {
		label: 'Administración',
		icon: 'security',
		open: false,
		children: [
			{
				label: 'Dashboard',
				icon: 'dashboard',
				name: 'home',
				perm: 'admin.dashboard',
			},
			{
				label: 'Usuarios',
				icon: 'people',
				name: 'users',
				perm: 'usuario.ver',
			},
			{
				label: 'Roles',
				icon: 'badge',
				name: 'RoleList',
				perm: 'admin.rol',
			},
			{
				label: 'Permisos',
				icon: 'policy',
				name: 'PermisoList',
				perm: 'admin.permiso',
			},
			{
				label: 'Acciones',
				icon: 'lock',
				name: 'ActionList',
				perm: 'admin.accion',
			},
			{
				label: 'Entidades',
				icon: 'format_list_bulleted',
				name: 'entity_list',
				perm: 'admin.entidad',
			},
		],
	}

	const filteredMenu = computed(() => {
		const base = [...menu]

		// Filtrar admin menu por permisos
		const filteredAdminChildren = adminMenu.children.filter((item) => !item.perm || can(item.perm))

		if (filteredAdminChildren.length > 0) {
			base.push(adminMenu)
		}

		return base
	})

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
				},
				{
					label: 'Buscar',
					icon: 'search',
					to: '',
				},
			],
		},
	])
</script>
