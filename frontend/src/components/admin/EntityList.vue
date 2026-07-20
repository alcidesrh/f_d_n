<template>
	<div class="content">
		<!-- <div class="dashboard__section-body"> -->
		<div class="mb-2rem flex justify-end">
			<q-select clearable outlined dense bg-color="white" v-model="model" use-input input-debounce="0" label="Filtrar" :options="options" @filter="filterFn" style="width: 250px">
				<template v-slot:no-option>
					<q-item>
						<q-item-section class="text-grey"> No hay resultados </q-item-section>
					</q-item>
				</template>
			</q-select>
		</div>
		<div v-if="entities.length" class="entities-grid">
			<!-- <EntityCard
				v-for="entity in options"
				:key="entity"
				:entity="schemaStore.entities[entity]"
				:icon_name="getEntityIcon(entity)"
				:record-count="recordCounts[entity]"
				:loading="loadingCounts"
			/> -->
			<div v-for="(entity, i) in options.map((v) => schemaStore.entities[v])" :key="i" class="entity-card">
				<div class="entity-card__icon">
					<icon :name="getEntityIcon(entity.name)" fill />
				</div>
				<div class="h-33px">
					<div class="entity-card__name">{{ entity.name }}</div>
					<div v-if="recordCounts[entity.name] != null" class="entity-card__count mt-5px">{{ recordCounts[entity.name].toLocaleString('es-BO') }} registros</div>
					<div v-else-if="loadingCounts" class="entity-card__count">cargando...</div>
				</div>
				<div class="entity-card__action flex gap-x-3 mt-10px justify-around w-full max-w-150px px-20px">
					<icon name="reorder" class="hover" @click="$router.push({ name: 'list', params: { entity: entity.name } })" />
					<Icon name="docs_add_on" class="hover" @click="$router.push({ name: 'form', params: { entity: entity.name } })"></Icon>
					<Icon
						name="settings"
						class="hover"
						@click="
							$router.push({
								name: 'entity_config',
								params: { entity: entity.name, action: 'editar' },
							})
						"
					></Icon>
				</div>
			</div>
		</div>
		<div v-else class="empty">
			<q-spinner-dots color="primary" size="2em" />
			<span>Cargando entidades...</span>
		</div>
		<!-- </div> -->
	</div>
</template>
<script setup lang="ts">
import { getEntityIcon } from '@/config/entityIcons'
import type { Entity } from '@/types/graphql'

const schemaStore = useSchemaStore()
function isEntity(name: string): boolean {
	return (
		!name.endsWith('Connection') &&
		!name.endsWith('Edge') &&
		!name.endsWith('PageInfo') &&
		!name.endsWith('Resource') &&
		!name.endsWith('Payload') &&
		!name.endsWith('PaginationInfo') &&
		!name.endsWith('Input') &&
		!name.startsWith('__')
	)
}
const recordCounts = ref<Record<string, number>>({})
const loadingCounts = ref(true)

const model = ref(null)
const options = ref(Object.keys(schemaStore.entities))
const stringOptions = ref(options.value)
const entities = computed<Entity[]>(() =>
	Object.entries(schemaStore.entities)
		.filter(([name]) => isEntity(name))
		.map(([, entity]) => entity),
)
watch(
	() => model.value,
	(v) => {
		if (!v) {
			options.value = stringOptions.value
			return
		}
		const needle = v.toLowerCase()
		options.value = stringOptions.value.filter((v) => v.toLowerCase().includes(needle))
	},
)
function filterFn(val, update) {
	if (val === '') {
		update(() => {
			options.value = stringOptions.value

			// here you have access to "ref" which
			// is the Vue reference of the QSelect
		})
		return
	}

	update(() => {
		const needle = val.toLowerCase()
		options.value = stringOptions.value.filter((v) => v.toLowerCase().includes(needle))
	})
}
onMounted(async () => {
	const api = useApi()
	const data = await api.get('/entity-record-counts')
	recordCounts.value = data
})
</script>
<style scoped lang="scss">
.entities-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	gap: 1rem;
}
.entity-card {
	background: #fff;
	border: 1px solid $surface-4;
	border-radius: 12px;
	padding: 1.25rem;
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
	gap: 0.5rem;
	transition:
		box-shadow 0.2s,
		transform 0.2s;
	cursor: default;

	&:hover {
		box-shadow: 0 4px 16px 2px $surface-3;
		transform: translateY(-2px);
	}

	&__icon {
		width: 48px;
		height: 48px;
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: color-mix(in srgb, $primary 10%, transparent);
		font-size: 1.75rem;
	}

	&__name {
		font-weight: 600;
		font-size: 1rem;
		color: $dark;
	}
	&__action {
		& > .hover {
			padding: 0.25rem;
			border-radius: 6px;
			// cursor: pointer;
			color: $surface-6;
			// transition: color 0.2s;
			// &:hover {
			// color: $primary;
			// }
		}
	}

	&__count {
		font-size: 0.75rem;
		color: $surface-6;
	}

	&__actions {
		display: flex;
		gap: 0.25rem;
		margin-top: 0.25rem;
	}
}
</style>
