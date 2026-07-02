<template>
	<aside class="left-sidebar">
		<div @click="sidebarStore.setMode()" class="sidebar-left-trigger" :class="{ close: sidebarStore.mode === 'close' }">
			<icon name="departure_board" class="trigger-icon" />
		</div>
		<div class="sidebar-control" :class="[mode]">
			<div class="toogle-wraper large" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
				<icon fill name="switch_left" class="text-20px font-medium" />
			</div>
			<div class="close-wraper" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : modeStates.close)">
				<icon name="close_small" class="text-20px font-medium" />
			</div>
		</div>

		<nav>
			<MenuLarge v-if="sidebarStore.mode == sidebarStore.modeStates.large" :store="sidebarStore" :menu="filteredMenu" />
			<MenuMini v-else-if="sidebarStore.mode == sidebarStore.modeStates.mini" :items="filteredMenu"> </MenuMini>
		</nav>
	</aside>
</template>

<script setup lang="ts">
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { useSidebarStore } from '@/stores/autoimport/sidebar'
	import { useQuasar } from 'quasar'

	const sidebarStore = useSidebarStore('sidebarLeft', 'left')
	const session = useUserSessionStore()
	const $q = useQuasar()

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
		const filteredAdminChildren = adminMenu.children.filter((item) => !item.perm)

		if (filteredAdminChildren.length > 0) {
			base.push(adminMenu)
		}

		return base
	})

	const { mode, modeStates } = storeToRefs(sidebarStore)

	const toogleSidebars = ref(['left-large', 'right-large'])
	let layoutClass = computed(() => toogleSidebars.value.join(' '))

	let leftState = 'large',
		rightState = 'large'
	function toogleSidebarMode() {
		leftState = leftState == 'large' ? 'mini' : 'large'
		toogleSidebars.value[0] = `left-${leftState}`
	}

	function setRight(state) {
		rightState = rightState == 'large' ? 'mini' : 'large'
		toogleSidebars.value[1] = `right-${rightState}`
	}
</script>
