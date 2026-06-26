<template>
	<q-page class="dashboard">
		<header class="dashboard__header">
			<div>
				<h1 class="dashboard__title">Panel de Administración</h1>
				<p class="dashboard__subtitle">Gestión y monitoreo del sistema de transporte</p>
			</div>
			<div class="dashboard__date">
				<Icon name="calendar_today" />
				{{ today }}
			</div>
		</header>

		<section class="dashboard__section">
			<div class="dashboard__section-header" @click="toggleSection('resumen')">
				<h2 class="dashboard__section-title">Resumen</h2>
				<icon :name="expandedSections.has('resumen') ? 'expand_less' : 'expand_more'" class="section-chevron" />
			</div>
			<div v-if="expandedSections.has('resumen')" class="dashboard__section-body">
				<div class="stats-grid">
					<StatsCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
				</div>
			</div>
		</section>

		<section class="dashboard__section">
			<div class="dashboard__section-header" @click="toggleSection('entities')">
				<h2 class="dashboard__section-title">Gestión de Entidades</h2>
				<icon :name="expandedSections.has('entities') ? 'expand_less' : 'expand_more'" class="section-chevron" />
			</div>
			<div v-if="expandedSections.has('entities')" class="dashboard__section-body">
				<p class="dashboard__section-desc">Acceda a las operaciones CRUD de cada entidad del sistema</p>
				<div v-if="entities.length" class="entities-grid">
					<EntityCard v-for="entity in entities" :key="entity.name" :entity="entity" :icon_name="getEntityIcon(entity.name)" :record-count="recordCounts[entity.name]" :loading="loadingCounts" />
				</div>
				<div v-else class="dashboard__empty">
					<q-spinner-dots color="primary" size="2em" />
					<span>Cargando entidades...</span>
				</div>
			</div>
		</section>
	</q-page>
</template>

<script setup lang="ts">
	import { getEntityIcon } from '@/config/entityIcons'
	import type { Entity } from '@/types/graphql'

	interface Stat {
		label: string
		value: string
		icon: string
		trend: 'up' | 'down' | 'neutral'
		percentage: string
		color: string
	}

	const schemaStore = useSchemaStore()
	const today = computed(() => new Date().toLocaleDateString('es-BO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }))

	const expandedSections = reactive(new Set(['resumen']))

	function toggleSection(name: string) {
		if (expandedSections.has(name)) {
			expandedSections.delete(name)
		} else {
			expandedSections.add(name)
		}
	}

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

	const entities = computed<Entity[]>(() =>
		Object.entries(schemaStore.entities)
			.filter(([name]) => isEntity(name))
			.map(([, entity]) => entity),
	)

	const recordCounts = ref<Record<string, number>>({})
	const loadingCounts = ref(true)

	onMounted(async () => {
		try {
			const api = useApi()
			const data = await api.get('/entity-record-counts')
			recordCounts.value = data
		} catch {
			// silently fail; cards show placeholder
		} finally {
			loadingCounts.value = false
		}
	})

	function rand(min: number, max: number): number {
		return Math.floor(Math.random() * (max - min + 1)) + min
	}

	function randTrend(): 'up' | 'down' | 'neutral' {
		const n = Math.random()
		if (n < 0.45) return 'up'
		if (n < 0.9) return 'down'
		return 'neutral'
	}

	const COLORS = ['#1976D2', '#21BA45', '#F2C037', '#E53935', '#9C27B0', '#00BCD4', '#FF5722', '#607D8B']

	const stats = computed<Stat[]>(() => [
		{
			label: 'Usuarios Activos',
			value: rand(800, 2500).toLocaleString('es-BO'),
			icon: 'group',
			trend: randTrend(),
			percentage: `${rand(2, 18)}%`,
			color: COLORS[0],
		},
		{
			label: 'Ventas Hoy',
			value: `Bs ${rand(15000, 85000).toLocaleString('es-BO')}`,
			icon: 'payments',
			trend: randTrend(),
			percentage: `${rand(3, 22)}%`,
			color: COLORS[1],
		},
		{
			label: 'Viajes Programados',
			value: rand(20, 65).toString(),
			icon: 'departure_board',
			trend: randTrend(),
			percentage: `${rand(1, 15)}%`,
			color: COLORS[2],
		},
		{
			label: 'Pasajeros Hoy',
			value: rand(300, 1800).toLocaleString('es-BO'),
			icon: 'people',
			trend: randTrend(),
			percentage: `${rand(2, 20)}%`,
			color: COLORS[3],
		},
		{
			label: 'Encomiendas en Tránsito',
			value: rand(40, 250).toLocaleString('es-BO'),
			icon: 'inventory_2',
			trend: randTrend(),
			percentage: `${rand(1, 12)}%`,
			color: COLORS[4],
		},
		{
			label: 'Flota Operativa',
			value: `${rand(12, 38)} / ${rand(20, 50)}`,
			icon: 'directions_bus',
			trend: randTrend(),
			percentage: `${rand(1, 10)}%`,
			color: COLORS[5],
		},
	])
</script>

<style scoped lang="scss">
	.dashboard {
		padding: 1.5rem;

		&__header {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 1rem;
			margin-bottom: 2rem;
			flex-wrap: wrap;
		}

		&__title {
			font-size: 1.625rem;
			font-weight: 700;
			color: $dark;
			margin: 0;
			line-height: 1.3;
		}

		&__subtitle {
			font-size: 0.9rem;
			color: $surface-6;
			margin: 0.25rem 0 0;
		}

		&__date {
			display: flex;
			align-items: center;
			gap: 0.4rem;
			font-size: 0.875rem;
			color: $surface-6;
			white-space: nowrap;
			padding-top: 0.25rem;
		}

		&__section {
			margin-bottom: 1rem;
			border: 1px solid $surface-4;
			border-radius: 10px;
			background: #fff;
			overflow: hidden;
		}

		&__section-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0.75rem 1rem;
			cursor: pointer;
			user-select: none;
			transition: background 0.15s;

			&:hover {
				background: $surface-1;
			}
		}

		&__section-title {
			font-size: 1rem;
			font-weight: 600;
			color: $dark;
			margin: 0;
		}

		.section-chevron {
			font-size: 1.25rem;
			color: $surface-6;
			transition: transform 0.2s;
		}

		&__section-body {
			padding: 0.25rem 1rem 1rem;
		}

		&__section-desc {
			font-size: 0.85rem;
			color: $surface-6;
			margin: 0 0 1rem;
		}

		&__empty {
			display: flex;
			align-items: center;
			gap: 1rem;
			justify-content: center;
			padding: 3rem;
			color: $surface-6;
			font-size: 0.9rem;
		}
	}

	.stats-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
		gap: 1rem;
	}

	.entities-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
		gap: 1rem;
	}

	@media (max-width: 599px) {
		.dashboard {
			padding: 1rem;

			&__header {
				flex-direction: column;
			}
		}

		.stats-grid {
			grid-template-columns: 1fr;
		}

		.entities-grid {
			grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		}
	}
</style>
