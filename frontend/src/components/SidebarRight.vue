<template>
	<aside class="right-sidebar">
		<div v-if="sidebarStore.mode === 'close'" class="sidebar-right-trigger" @click="sidebarStore.setMode('large')">
			<icon name="tune" wght="300" class="trigger-icon" />
		</div>

		<div class="sidebar-control" :class="[mode]">
			<div class="toogle-wraper large" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
				<icon fill name="switch_right" class="text-20px font-medium" />
			</div>
			<div class="close-wraper" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : modeStates.close)">
				<icon name="close_small" class="text-20px font-medium" />
			</div>
		</div>
		<div class="sidebar-panel">
			<!-- Large mode -->
			<div v-if="sidebarStore.mode === sidebarStore.modeStates.large" class="panel-content">
				<div class="change-password-item" @click="changePasswordModal = true">
					<icon name="manage_accounts" class="cp-icon" />
					<span class="cp-label">Cambiar Contraseña</span>
				</div>

				<div class="section-divider" />

				<SectionTree v-for="(sec, i) in filteredSections" :key="i" :item="sec" :depth="0" @action="handleAction" />
			</div>

			<!-- Mini mode -->
			<div v-if="sidebarStore.mode === sidebarStore.modeStates.mini" class="panel-mini">
				<div class="mini-cp-wrapper">
					<icon name="manage_accounts" class="mini-icon cp-highlight" @click="changePasswordModal = true" />
				</div>
				<div class="section-divider mini-div" />

				<SectionMini :items="filteredSections" @action="handleAction" />
			</div>
		</div>

		<ChangePasswordModal v-model="changePasswordModal" />
	</aside>
</template>

<script setup lang="ts">
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { useSidebarStore } from '@/stores/autoimport/sidebar'
	import type { Seccion } from '@/types/seccion'
	import { useQuasar } from 'quasar'

	const sidebarStore = useSidebarStore('sidebarRight', 'right')
	const { mode, modeStates } = storeToRefs(sidebarStore)

	const session = useUserSessionStore()
	const $q = useQuasar()

	const changePasswordModal = ref(false)

	const adminMenu: Seccion = {
		label: 'Seguridad',
		icon: 'security',
		children: [
			{
				label: 'Usuarios',
				icon: 'people',
				name: 'users',
				perm: ['usuario.ver'],
				children: [
					{
						label: 'Buscar',
						icon: 'search',
						name: 'RoleList',
						perm: ['admin.rol'],
						children: [
							{
								label: 'Roles',
								icon: 'badge',
								name: 'RoleList',
								perm: ['admin.rol'],
							},
							{
								label: 'Permisos',
								icon: 'policy',
								name: 'PermisoList',
								perm: ['admin.permiso'],
							},
						],
					},
				],
			},
			{
				label: 'Roles',
				icon: 'badge',
				name: 'RoleList',
				perm: ['admin.rol'],
				children: [
					{
						label: 'Usuarios',
						icon: 'people',
						name: 'users',
						perm: ['usuario.ver'],
						children: [
							{
								label: 'Buscar',
								icon: 'search',
								name: 'RoleList',
								perm: ['admin.rol'],
								children: [
									{
										label: 'Roles',
										icon: 'badge',
										name: 'RoleList',
										perm: ['admin.rol'],
									},
									{
										label: 'Permisos',
										icon: 'policy',
										name: 'PermisoList',
										perm: ['admin.permiso'],
										children: [
											{
												label: 'Usuarios',
												icon: 'people',
												name: 'users',
												perm: ['usuario.ver'],
												children: [
													{
														label: 'Buscar',
														icon: 'search',
														name: 'RoleList',
														perm: ['admin.rol'],
														children: [
															{
																label: 'Roles',
																icon: 'badge',
																name: 'RoleList',
																perm: ['admin.rol'],
															},
															{
																label: 'Permisos',
																icon: 'policy',
																name: 'PermisoList',
																perm: ['admin.permiso'],
															},
														],
													},
												],
											},
										],
									},
								],
							},
						],
					},
				],
			},
			{
				label: 'Permisos',
				icon: 'policy',
				name: 'PermisoList',
				perm: ['admin.permiso'],
			},
			{
				label: 'Acciones',
				icon: 'lock',
				name: 'ActionList',
				perm: ['admin.accion'],
			},
			{
				label: 'Entidades',
				icon: 'format_list_bulleted',
				name: 'entity_list',
				perm: ['admin.entidad'],
			},
		],
	}

	const sections: Seccion[] = [
		{
			label: 'General',
			name: 'general',
			children: [
				{ label: 'Mi Cuenta', name: 'account', icon: 'person_edit' },
				{ label: 'Configuración de Ventas', name: 'sales-config', icon: 'settings' },
			],
		},
		{
			label: 'Estadísticas',
			name: 'estadisticas',
			children: [
				{ label: 'Mis Ventas', name: 'my-sales', icon: 'bar_chart' },
				{ label: 'Salidas Canceladas', name: 'cancelled', icon: 'event_busy' },
			],
		},
		adminMenu,
	]

	function canAccess(perm?: string | string[]): boolean {
		if (!perm) return true
		const codes = Array.isArray(perm) ? perm : [perm]
		return codes.some((p) => session.can(p))
	}

	function filterSections(items: Seccion[]): Seccion[] {
		return items
			.filter((item) => canAccess(item.perm))
			.map((item) => ({
				...item,
				children: item.children ? filterSections(item.children) : undefined,
			}))
			.filter((item) => item.children === undefined || item.children.length > 0)
	}

	const filteredSections = computed(() => filterSections(sections))

	function handleAction(name: string) {
		$q.notify({
			type: 'info',
			message: `Acción: ${name} (pendiente de implementar)`,
			timeout: 2000,
		})
	}
</script>

<style scoped lang="scss">
	.change-password-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.6rem 0.5rem;
		margin: 0.25rem 0;
		border-radius: 8px;
		cursor: pointer;
		background: $primary;
		transition: opacity 0.15s;

		&:hover {
			opacity: 0.85;
		}

		.cp-icon {
			font-size: 20px;
			color: white;
		}

		.cp-label {
			font-size: 0.85rem;
			font-weight: 500;
			color: white;
		}
	}

	.cp-highlight {
		color: $primary !important;
	}

	.sidebar-panel {
		padding: 0.5rem;
		height: 100%;
		overflow-y: auto;
	}

	.panel-content {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	.section-divider {
		height: 1px;
		background: $surface-4;
		margin: 0.25rem 0.5rem;

		&.mini-div {
			margin: 0.5rem 0.25rem;
		}
	}

	.panel-mini {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 0.5rem 0;
		gap: 0.5rem;

		.mini-cp-wrapper {
			.mini-icon {
				font-size: 22px;
				cursor: pointer;
				padding: 4px;
				border-radius: 6px;
				transition: background 0.15s;

				&:hover {
					background: $surface-2;
				}
			}
		}
	}
</style>
