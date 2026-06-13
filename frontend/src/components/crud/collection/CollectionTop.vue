<template>
	<div v-if="store" class="flex justify-between w-full ml-auto mb-20px">
		<span data-tootik="Tootik Text" data-tootik-conf="shadow right">{{ store.name }}</span>
		<div class="flex table-options text-surface-2">
			<div @click="$router.push({ path: `/form/${store.nameDecapitalize}` })" :data-tootik="`Agregar ${store.name}`" data-tootik-conf="">
				<icon name="add" />
			</div>
			<div :class="{ active: open }" class="relative" data-tootik="Ocultar columnas">
				<icon name="add_column_right" class="">
					<q-badge v-if="!visibleAllColumns" color="primary" floating class="font-medium" rounded>{{ store.columns.length - store.visibleColumns.length }}</q-badge>
					<q-menu v-model="open" transition-show="flip-left" transition-hide="flip-right">
						<q-card class="my-card" style="width: 100%; max-width: 500px; min-width: 230px">
							<q-card-section>
								<div class="row items-center gap-5" :class="{ 'opacity-50': visibleAllColumns }">
									<div class="col font-semibold">Mostrar todas</div>
									<div class="col-auto">
										<q-toggle size="xs" v-model="visibleAllColumns" :disable="visibleAllColumns" />
									</div>
								</div>
								<q-separator inset my-2 />
								<template v-for="(col, i) in store.config.collectionFieldConfig.filter((v) => v.visible)" :key="i">
									<div class="u-px-sm">
										<div class="row items-center gap-5 mb-3">
											<div class="col u-text-0 font-medium">
												{{ col.label }}
											</div>
											<div class="col-auto">
												<q-toggle size="xs" v-model="store.visibleColumns" :val="col.field" :disable="store.visibleColumns.length == 1 && store.visibleColumns[0] == col.field" />
											</div>
										</div>
									</div>
								</template>
							</q-card-section>
						</q-card>
					</q-menu>
				</icon>
			</div>
			<div :class="{ active: !toggleAction }" data-tootik="Seleccionar filas">
				<icon @click="setToggleAction" name="checklist_rtl" />
			</div>
			<div :class="{ active: inFullscreen }" data-tootik="Pantalla completa">
				<icon :name="inFullscreen ? 'recenter' : 'fullscreen'" @click="$emit('toggleFullscreen')" />
			</div>

			<div data-tootik="Valores por defecto">
				<icon @click="reset" name="autorenew" />
			</div>
		</div>
		<!-- </div> -->
	</div>
</template>
<script setup lang="ts">
	import { StateStore } from '@/types/graphql'

	// import { EntityInterface } from "@/types/entity";

	interface Props {
		// entity: EntityInterface;
		inFullscreen: Boolean
	}
	const open = ref(false)
	const { inFullscreen } = defineProps<Props>()
	const emit = defineEmits<{
		(e: 'reload'): void
		(e: 'toggleFullscreen'): void
		(e: 'toggleSelectionMode'): void
		(e: 'reset'): void
	}>()

	const store: Ref<StateStore> = ref()
	// const collection = store.items,
	const toggleAction = ref(true)

	const visibleColumns = ref()

	const visibleAllColumns = ref(true)

	function setToggleAction() {
		// if (!toggleAction.value) {
		emit('toggleSelectionMode')
		// }
		toggleAction.value = !toggleAction.value
	}

	watch(
		() => visibleAllColumns.value,
		(v) => {
			if (v) {
				store.value.visibleColumns = []
				store.value.columns.forEach((v) => {
					v.visible = true
					store.value.visibleColumns.push(v.field)
				})
			}
		},
	)

	function reset() {
		if (inFullscreen) {
			emit('toggleFullscreen')
		}
		if (!toggleAction.value) {
			setToggleAction()
		}
		store.value.resetColumns()
		store.value.pagination.currentPage = 1
		emit('reset')
		emit('reload')
	}

	onBeforeMount(async () => {
		store.value = await getStore()

		// visibleColumns.value = store?.value.visibleColumns;
		visibleAllColumns.value = store.value.visibleColumns.length == store.value.columns.length

		watch(
			() => store.value.visibleColumns,
			(v) => {
				visibleAllColumns.value = v.length == store.value.columns.length
				const t = [],
					t2 = []
				store.value?.columns.forEach((v2) => {
					if (v.includes(v2.field)) {
						v2.visible = true
						t.push(v2)
					} else {
						v2.visible = false
						t2.push(v2)
					}
				})
				store.value.columns = [...t, ...t2].map((v, i) => {
					v.position = i + 1
					return v
				})
				emit('reload')
			},
		)
	})
</script>
<style lang="scss">
	.table-options {
		display: flex;
		align-items: center;
		& > div {
			box-shadow: -1px 2px 1px $surface-3;
			border: 1px solid $surface-3;
			border-radius: 4px;
			margin: 3px;
			cursor: pointer;
			// width: 40px;
			height: 100%;
			text-align: center;
			display: flex;
			align-items: center;
			justify-content: center;
			// border: 1px solid $surface-4;
			background-color: white;

			padding: 3px 10px;
			&:hover {
				background-color: $surface-2;
			}
			& > .fdn-icon {
				font-size: 1.2rem;
				font-weight: 500;
				color: $surface-6;
			}
			&.active {
				background-color: $surface-6;
				color: $surface-1;
				&.active {
					font-weight: 600;
					border-right: 1px solid $surface-4;
					border-left: 1px solid $surface-5;
				}
			}
		}
	}
</style>
