<template>
	<div v-if="store" class="flex justify-between w-full ml-auto">
		<span class="text-h5 font-medium">{{ store.name }}</span>
		<div class="flex table-options text-surface-2">
			<div @click="$router.push({ path: `/form/${store.nameDecapitalize}` })" :data-tootik="`Agregar ${store.name}`" data-tootik-conf="">
				<icon name="add" />
			</div>
			<div :class="{ active: open }" class="relative" data-tootik="Ocultar columnas">
				<icon name="add_column_right" class=""> </icon>
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
			</div>
			<div @click="setToggleAction" :class="{ active: !toggleAction }" data-tootik="Seleccionar filas">
				<icon name="checklist_rtl" />
			</div>
			<div @click="$emit('toggleFullscreen')" :class="{ active: inFullscreen }" data-tootik="Pantalla completa">
				<icon :name="inFullscreen ? 'recenter' : 'fullscreen'" />
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
	gap: 7px;
	& > div {
		padding: 6px 12px;
		font-size: 16px;
		font-weight: 600;
		color: #222;
		background: #f5f5f5;
		background-color: #fff;

		// border: 1px solid $surface-4;
		border-radius: 4px;
		cursor: pointer;
		box-shadow: 0 1px 2px 0.5px $surface-5;
		// 0 2px 4pxs 0px $surface-4,
		// 0 2px 1px 0px $surface-4;
		transition:
			box-shadow 1.5s,
			transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
		&.active {
			background-color: $surface-2;
			opacity: 0.6;
			box-shadow:
				// inset 0 2px 4px 0 $surface-4,
				inset 0 1.5px 3px 0.5px $surface-5;
			transform: translateY(2px);
		}
	}
	// & > div {

	// 	box-shadow: 1.5px 1.5px 2.5px 0px $surface-5;
	// 	border: 1px solid $surface-4;
	// 	border-right: none;
	// 	&:last-child {
	// 		border-right: 1px solid $surface-4;
	// 	}
	// 	// border-radius: 4px;
	// 	// margin: 5px;
	// 	margin-top: 0px;
	// 	cursor: pointer;
	// 	text-align: center;
	// 	display: flex;
	// 	align-items: center;
	// 	justify-content: center;
	// 	background-color: $surface-2;
	// 	transition:
	// 		background-color $transition-time,
	// 		font-weight $transition-time;
	// 	padding: 6px 12px;
	// 	&:hover {
	// 		background-color: $surface-3;
	// 		& > .fdn-icon {
	// 			font-weight: 700;
	// 		}
	// 	}
	// 	& > .fdn-icon {
	// 		// font-size: 0.8rem;
	// 		// font-weight: 700;
	// 		color: $surface-6;
	// 	}
	// 	&.active {
	// 		// background-color: $surface-6;
	// 		box-shadow: 1px 1px 3.5px 0px $surface-6 inset;

	// 		// font-weight: 600;
	// 		// border-right: 1px solid $surface-4;
	// 		// border-left: 1px solid $surface-5;
	// 		& > .fdn-icon {
	// 			// color: $surface-2;
	// 		}
	// 	}
	// }
}
</style>
