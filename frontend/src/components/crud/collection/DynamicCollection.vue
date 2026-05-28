<template>
	<div v-if="store && store.columns.length" class="relative">
		<!-- <div class="absolute left-50% top-20px z-2000 bg-slate-2">
			<pre>  {{ store.filters }}</pre>
		</div> -->
		<FormKit v-model="store.filters" type="form" :actions="false" form-class="block m-auto" :config="{ wrapperClass: 'mb-0!' }">
			<q-table
				:bordered="$q.screen.xs"
				:grid="$q.screen.xs"
				:dense="$q.screen.sm"
				flat
				class="sticky-header-table"
				:rows="store.items"
				:columns="store.computedColumns"
				:visible-columns="store.visibleColumns"
				row-key="id"
				v-model:pagination="paginationQuasar"
				:rows-per-page-options="[25, 40, 50, 70, 100, 150, 200, 0]"
				:loading="!!cloading"
				:table-style="`max-height: ${mxh}px;`"
				binary-state-sort
				@request="onRequest"
				:class="{
					loading: !!cloading,
				}"
				selection="multiple"
				v-model:selected="selected"
			>
				<template v-slot:top="props">
					<CollectionTop
						:inFullscreen="props.inFullscreen"
						@reload="store.collection"
						@toggle-fullscreen="props.toggleFullscreen"
						@toggle-selection-mode="toggleSelectionMode"
						@reset="reset"
					/>
				</template>
				<template v-slot:header="props">
					<CollectionHeader
						:selection-mode="selectionMode"
						:selected="selected"
						:data="props"
						:clear="clear"
						@remove-multiple="store.removeMultiple(selected)"
						@order-columns="store.orderColumns"
					/>
				</template>
				<template v-slot:body="props">
					<CollectionBody :data="props" :selection-mode="selectionMode" :class="{ 'opacity-50': loading }">
						<template #actions_row>
							<div flex justify-center items-center gap-1>
								<icon
									@click="
										$router.push({
											name: 'form',
											params: {
												entity: store?.nameDecapitalize,
												id: getIdFromIri(props.row.id),
											},
										})
									"
									round
									name="stylus"
									class="border-surface-4 rounded-full text-22px lg:text-22px"
								/>
								<icon @click="store.remove(props.row)" name="delete" class="border-surface-4 rounded-full text-22px" />
							</div>
						</template>
					</CollectionBody>
				</template>
			</q-table>
		</FormKit>
	</div>
	<div v-else>
		<ListPreload :cols="7" />
	</div>
</template>

<script setup lang="ts">
	import { PaginationQuasar } from '@/types/collection'
	import { StateStore } from '@/types/graphql'
	import { useCloned } from '@vueuse/core'
	import { useQuasar } from 'quasar'

	const $q = useQuasar()
	const mxh = computed(() => $q.screen.height - 270)
	const store = ref() as Ref<StateStore>
	const paginationQuasar = ref({}) as Ref<PaginationQuasar>
	watchEffect(() => {
		if (store.value) {
			paginationQuasar.value.page = store.value.pagination.currentPage
			paginationQuasar.value.rowsPerPage = store.value.pagination.itemsPerPage
			paginationQuasar.value.rowsNumber = store.value.pagination.totalCount
			paginationQuasar.value.descending = store.value.orderType == 'DESC'
			paginationQuasar.value.sortBy = store.value.orderField
		}
	})
	const selected = ref([]),
		selectionMode = ref(false),
		clear = ref(false)

	function reset() {
		selected.value = []
		clear.value = !clear.value
	}

	function removeMultiple() {
		store.value.removeMultiple(useCloned(selected.value).cloned)
		selected.value = []
	}

	function onRequest({ pagination, filter }: Record<'pagination', PaginationQuasar>) {
		store.value.pagination.currentPage = pagination.page
		store.value.pagination.itemsPerPage = pagination.rowsPerPage
		store.value.pagination.totalCount = pagination.rowsNumber
		store.value.orderField = pagination.sortBy
		store.value.orderType = pagination.descending ? 'DESC' : 'ASC'
		store.value.collection()
	}
	function toggleSelectionMode() {
		if (selectionMode.value) {
			selected.value = []
		}
		selectionMode.value = !selectionMode.value
	}

	onBeforeMount(async () => {
		store.value = await getStore()
		// const { items } = storeToRefs(store.value);
		paginationQuasar.value.sortBy = store.value.orderField
		paginationQuasar.value.descending = store.value.orderType == 'DESC'
		paginationQuasar.value.page = store.value.pagination.currentPage
		paginationQuasar.value.rowsPerPage = store.value.pagination.itemsPerPage
		paginationQuasar.value.rowsNumber = store.value.pagination.totalCount
	})
</script>

<style scoped lang="scss">
	::highlight(highlight-0),
	::highlight(highlight-1),
	::highlight(highlight-2),
	::highlight(highlight-3),
	::highlight(highlight-4),
	::highlight(highlight-5),
	::highlight(highlight-6),
	::highlight(highlight-7) {
		background-color: $highlight;
		color: black;
	}
</style>
