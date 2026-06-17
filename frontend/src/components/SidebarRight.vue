<template>
	<SidebarDrawer store-id="sidebarRight" position="right">
		<template #content="{ data }">
			<div class="px-[0.5em]">
				<Divider align="center" type="dotted">
					<b>Seguridad</b>
				</Divider>
				<AMenu :mode="data.mode" :menu="menuStore.menu" :root="true" :toggle="toggle" position="right" @toggle="toggle = !toggle" />

				<Divider align="center" type="dotted">
					<b>Center</b>
				</Divider>
				<AMenu :mode="data.mode" :menu="menu2" :root="true" :toggle="toggle" position="right" @toggle="toggle = !toggle" />
				<Divider align="center" type="dotted">
					<b>Empresa</b>
				</Divider>
			</div>
		</template>
	</SidebarDrawer>
</template>
<script setup lang="ts">
	const menu = ref([
		// {
		//   label: "Limpiar cache",
		//   icon: "gamepad_up",
		//   name: "refresh",
		//   type: "action",
		//   command: () => {
		//     fdn.value.refresh();
		//   },
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
					params: { id: user.value.username },
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
	])

	const menu2 = ref([
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
					params: { id: user.value.username },
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
	])
	const menuStore = useMenuStateStore('menu-right', menu.value)
	const { toggle } = storeToRefs(menuStore)

	const items = ref([
		{
			label: 'Files',
			icon: 'pi pi-file',
			items: [
				{
					label: 'Documents',
					icon: 'pi pi-file',
					items: [
						{
							label: 'Invoices',
							icon: 'pi pi-file-pdf',
							items: [
								{
									label: 'Pending',
									icon: 'pi pi-stop',
								},
								{
									label: 'Paid',
									icon: 'pi pi-check-circle',
								},
							],
						},
						{
							label: 'Clients',
							icon: 'pi pi-users',
						},
					],
				},
				{
					label: 'Images',
					icon: 'pi pi-image',
					items: [
						{
							label: 'Logos',
							icon: 'pi pi-image',
						},
					],
				},
			],
		},
		{
			label: 'Cloud',
			icon: 'pi pi-cloud',
			items: [
				{
					label: 'Upload',
					icon: 'pi pi-cloud-upload',
				},
				{
					label: 'Download',
					icon: 'pi pi-cloud-download',
				},
				{
					label: 'Sync',
					icon: 'pi pi-refresh',
				},
			],
		},
		{
			label: 'Devices',
			icon: 'pi pi-desktop',
			items: [
				{
					label: 'Phone',
					icon: 'pi pi-mobile',
				},
				{
					label: 'Desktop',
					icon: 'pi pi-desktop',
				},
				{
					label: 'Tablet',
					icon: 'pi pi-tablet',
				},
			],
		},
	])
</script>
