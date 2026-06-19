<template>
	<q-layout view="hHh LpR fFf">
		<Notify />
		<Topbar />
		<SidebarLeft />

		<q-page-container class="main-content h-[100vh] relative" :class="[sidebarStore.position, mode]" :style="rightPadStyle">
			<q-page class="h-full u-p-xs lg:u-px-l m-auto relative">
				<RouterView v-slot="{ Component, route }">
					<transition :name="route.meta.transition || 'route'" mode="out-in">
						<component :is="Component" :key="route.name" />
					</transition>
				</RouterView>
			</q-page>
		</q-page-container>
		<SidebarRight />
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
	const rightSidebar = useSidebarStore('sidebarRight', 'right')
	const loadingStore = useLoadingStore()
	const session = useUserSessionStore()
	const { can } = usePermission()

	const { mode, modeStates } = storeToRefs(sidebarStore)

	const SMALL = 57
	const LARGE = 184
	const rightPadStyle = computed(() => {
		const m = rightSidebar.mode
		return { paddingRight: m === 'close' ? '0px' : m === 'mini' ? SMALL + 'px' : LARGE + 'px' }
	})

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
