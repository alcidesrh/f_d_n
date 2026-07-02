<template>
	<aside class="right-sidebar">
		<div @click="sidebarStore.setMode()" class="sidebar-right-trigger" :class="{ close: sidebarStore.mode === 'close' }">
			<icon name="tune" wght="300" class="trigger-icon" />
		</div>

		<div class="sidebar-control" :class="[mode]">
			<div class="toogle-wraper large" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
				<icon fill name="switch_left" class="text-20px font-medium" />
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

		<div v-if="sidebarStore.mode === sidebarStore.modeStates.large" class="sidebar-dev-controls">
			<div class="dev-row">
				<q-btn flat dense icon="sym_o_sync" label="Refrescar Entidades" class="full-width dev-btn" @click="refreshSchema" />
			</div>
			<div class="dev-row entity-row">
				<q-select v-model="selectedEntity" :options="entityOptions" dense outlined placeholder="Entidad..." class="col" />
				<q-btn flat dense icon="sym_o_refresh" class="dev-icon-btn" @click="refreshEntityStore" :disable="!selectedEntity" />
			</div>
		</div>
		<div v-else-if="sidebarStore.mode === sidebarStore.modeStates.mini" class="sidebar-dev-controls sidebar-dev-controls-mini">
			<q-btn flat dense icon="sync" size="sm" @click="refreshSchema" />
			<q-btn flat dense icon="refresh" size="sm" @click="entityDialog = true" />
		</div>

		<q-dialog v-model="entityDialog" position="bottom">
			<q-card style="min-width: 220px">
				<q-card-section class="q-pt-md">
					<div class="text-h6">Refrescar entidad</div>
				</q-card-section>
				<q-card-section>
					<q-select v-model="selectedEntity" :options="entityOptions" dense outlined label="Entidad" />
				</q-card-section>
				<q-card-actions align="right" class="q-px-md q-pb-md">
					<q-btn flat label="Cancelar" v-close-popup />
					<q-btn flat label="Refrescar" color="primary" v-close-popup @click="refreshEntityStore" :disable="!selectedEntity" />
				</q-card-actions>
			</q-card>
		</q-dialog>

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

	const schemaStore = useSchemaStore()
	const selectedEntity = ref<string | null>(null)
	const entityDialog = ref(false)
	const entityOptions = computed(() => {
		return Object.keys(schemaStore.entities).sort()
	})

	async function refreshSchema() {
		try {
			await schemaStore.loadEntities()
			$q.notify({ type: 'positive', message: 'Metadata de entidades actualizada', timeout: 2000 })
		} catch {
			$q.notify({ type: 'negative', message: 'Error al refrescar metadata', timeout: 3000 })
		}
	}

	async function refreshEntityStore() {
		if (!selectedEntity.value) return
		try {
			const store = await getStore(selectedEntity.value, true)
			if (store) {
				$q.notify({ type: 'positive', message: `Store "${selectedEntity.value}" actualizada`, timeout: 2000 })
			}
		} catch {
			$q.notify({ type: 'negative', message: `Error al refrescar "${selectedEntity.value}"`, timeout: 3000 })
		}
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
		overflow-y: auto;
	}

	.sidebar-dev-controls {
		.dev-row {
			margin-bottom: 0.25rem;

			&:last-child {
				margin-bottom: 0;
			}
		}

		.dev-btn {
			font-size: 0.75rem;
			padding: 0.25rem;
			min-height: 28px;
		}

		.entity-row {
			display: flex;
			align-items: center;
			gap: 0.25rem;
		}

		.dev-icon-btn {
			font-size: 18px;
			padding: 4px;
		}

		:deep(.q-select) {
			font-size: 0.75rem;
		}

		:deep(.q-field__control) {
			min-height: 28px;
			padding: 0 6px;
		}

		:deep(.q-field__native) {
			padding: 0;
			min-height: 28px;
		}
	}

	.sidebar-dev-controls-mini {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.125rem;
		padding: 0.375rem 0;
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
