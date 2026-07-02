<template>
	<div class="dashboard">
		<div class="dashboard__header">
			<div>
				<h1 class="dashboard__title text-blue-grey-6">Panel de Administración</h1>
				<p class="dashboard__subtitle">Gestión y monitoreo del sistema de transporte</p>
			</div>
			<div class="dashboard__date">
				<Icon name="calendar_today" />
				{{ today }}
			</div>
		</div>
		<div class="accordion">
			<article class="item active">
				<button class="header">
					<span>Resumen</span>

					<svg class="icon" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
					</svg>
				</button>

				<div class="body">
					<div class="content">
						<!-- <div class="dashboard__section-body" :class="{ collapse: !expandedSections.has('resumen') }"> -->
						<div class="stats-grid">
							<StatsCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
						</div>
						<!-- </div> -->
					</div>
				</div>
			</article>

			<article class="item">
				<button class="header">
					<span>Gestión de Entidades</span>

					<svg class="icon" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
					</svg>
				</button>
				<div class="body">
					<div class="content">
						<!-- <div class="dashboard__section-body"> -->
						<p class="dashboard__section-desc">Acceda a las operaciones CRUD de cada entidad del sistema</p>
						<div v-if="entities.length" class="entities-grid">
							<EntityCard
								v-for="entity in entities"
								:key="entity.name"
								:entity="entity"
								:icon_name="getEntityIcon(entity.name)"
								:record-count="recordCounts[entity.name]"
								:loading="loadingCounts"
							/>
						</div>
						<div v-else class="empty">
							<q-spinner-dots color="primary" size="2em" />
							<span>Cargando entidades...</span>
						</div>
						<!-- </div> -->
					</div>
				</div>
			</article>
		</div>
	</div>
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

			gsap.value.registerPlugin()

			const items = document.querySelectorAll('.item')

			items.forEach((item) => {
				const header = item.querySelector('.header')
				const body = item.querySelector('.body')
				const icon = item.querySelector('.icon')

				if (item.classList.contains('active')) {
					gsap.value.set(body, {
						height: 'auto',
					})

					gsap.value.set(icon, {
						rotate: 45,
					})

					gsap.value.set(item, {
						boxShadow: '0 18px 40px rgb(0 0 0 /.12)',
					})
				}

				header.addEventListener('click', () => {
					const opened = item.classList.contains('active')

					items.forEach(closeItem)

					if (!opened) {
						openItem(item)
					}
				})
			})

			function openItem(item) {
				const body = item.querySelector('.body')
				const icon = item.querySelector('.icon')

				item.classList.add('active')

				gsap.value
					.timeline({
						defaults: {
							ease: 'power3.out',
						},
					})

					.to(
						item,
						{
							boxShadow: '0 20px 45px rgb(0 0 0 /.12)',
							duration: 0.35,
						},
						0,
					)

					.to(
						icon,
						{
							rotate: 45,
							duration: 0.45,
							ease: 'back.out(2)',
						},
						0,
					)

					.to(
						body,
						{
							height: 'auto',
							duration: 0.55,
							ease: 'expo.out',
						},
						0,
					)

					.fromTo(
						body.firstElementChild,
						{
							opacity: 0,
							y: -16,
						},
						{
							opacity: 1,
							y: 0,
							duration: 0.35,
							ease: 'power2.out',
						},
						'-=.25',
					)
			}

			function closeItem(item) {
				if (!item.classList.contains('active')) return

				item.classList.remove('active')

				const body = item.querySelector('.body')
				const icon = item.querySelector('.icon')

				gsap.value
					.timeline()

					.to(body.firstElementChild, {
						opacity: 0,
						y: -12,
						duration: 0.18,
					})

					.to(
						body,
						{
							height: 0,
							duration: 0.42,
							ease: 'expo.inOut',
						},
						'<',
					)

					.to(
						icon,
						{
							rotate: 0,
							duration: 0.3,
						},
						'<',
					)

					.to(
						item,
						{
							boxShadow: '0 5px 18px rgb(0 0 0 /.04)',
							duration: 0.3,
						},
						'<',
					)
			}
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

<style lang="scss">
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
	}
	.accordion {
		// width: min(700px, 95%);
		margin: 60px auto;

		& > .item {
			overflow: hidden;

			border-radius: 16px;

			margin-bottom: 16px;

			background: white;

			border: 1px solid #e7ebf2;

			// box-shadow: 0 3px 8px $surface-6;

			& > .header {
				width: 100%;
				padding: 22px 26px;
				display: flex;
				justify-content: space-between;
				align-items: center;
				cursor: pointer;
				border: none;
				background: none;
				font-size: 18px;
				font-weight: 600;
				& > .icon {
					width: 20px;
					height: 20px;
				}
			}
			& > .body {
				height: 0;
				overflow: hidden;
				& > .content {
					// padding: 0 26px 26px;

					// color: #606b80;

					// line-height: 1.7;

					padding: 0.25rem 1rem 1rem;
					display: grid;
					grid-template-rows: 1fr;
					transition: grid-template-rows 1s ease-out;
					overflow: hidden;
					// min-height: 0;
					& > .stats-grid {
						display: grid;
						grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
						gap: 1rem;
					}

					& > .entities-grid {
						display: grid;
						grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
						gap: 1rem;
					}
					& > .empty {
						display: flex;
						align-items: center;
						gap: 1rem;
						justify-content: center;
						padding: 3rem;
						color: $surface-6;
						font-size: 0.9rem;
					}

					// @media (max-width: 599px) {
					// 	.dashboard {
					// 		padding: 1rem;

					// 		&__header {
					// 			flex-direction: column;
					// 		}
					// 	}

					// 	.stats-grid {
					// 		grid-template-columns: 1fr;
					// 	}

					// 	.entities-grid {
					// 		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
					// 	}
					// }
				}
			}
		}
	}
	@media (max-width: 599px) {
		.dashboard {
			padding: 1rem;
		}
		.accordion > .item > .header {
			flex-direction: column;
		}
		.accordion > .item > .body > .content > .stats-grid {
			grid-template-columns: 1fr;
		}

		.accordion > .item > .body > .content > .entities-grid {
			grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		}
	}

	// // .dashboard__section:deep() {
	// // 	display: none !important;
	// // 	margin-bottom: 1rem;
	// // 	border: 1px solid $surface-4;
	// // 	border-radius: 10px;
	// // 	background: #fff;
	// // 	overflow: hidden;
	// // }
	// .dashboard {
	// 	padding: 1.5rem;

	// 	&__header {
	// 		display: flex;
	// 		align-items: flex-start;
	// 		justify-content: space-between;
	// 		gap: 1rem;
	// 		margin-bottom: 2rem;
	// 		flex-wrap: wrap;
	// 	}

	// 	&__title {
	// 		font-size: 1.625rem;
	// 		font-weight: 700;
	// 		color: $dark;
	// 		margin: 0;
	// 		line-height: 1.3;
	// 	}

	// 	&__subtitle {
	// 		font-size: 0.9rem;
	// 		color: $surface-6;
	// 		margin: 0.25rem 0 0;
	// 	}

	// 	&__date {
	// 		display: flex;
	// 		align-items: center;
	// 		gap: 0.4rem;
	// 		font-size: 0.875rem;
	// 		color: $surface-6;
	// 		white-space: nowrap;
	// 		padding-top: 0.25rem;
	// 	}

	// 	&__section {
	// 		margin-bottom: 1rem;
	// 		border: 1px solid $surface-4;
	// 		border-radius: 10px;
	// 		background: #fff;
	// 		overflow: hidden;
	// 	}

	// 	&__section-header {
	// 		display: flex;
	// 		align-items: center;
	// 		justify-content: space-between;
	// 		padding: 0.75rem 1rem;
	// 		cursor: pointer;
	// 		user-select: none;
	// 		transition: background 0.15s;

	// 		&:hover {
	// 			background: $surface-1;
	// 		}
	// 	}

	// 	&__section-title {
	// 		font-size: 1rem;
	// 		font-weight: 600;
	// 		color: $dark;
	// 		margin: 0;
	// 	}

	// 	.section-chevron {
	// 		font-size: 1.25rem;
	// 		color: $surface-6;
	// 		transition: transform 0.2s;
	// 	}

	// 	&__section-body {
	// 		padding: 0.25rem 1rem 1rem;
	// 		display: grid;
	// 		grid-template-rows: 1fr;
	// 		transition: grid-template-rows 1s ease-out;
	// 		overflow: hidden;
	// 		// min-height: 0;
	// 		& > * {
	// 			min-height: 20px;
	// 		}
	// 		&.collapse {
	// 			grid-template-rows: 0fr;
	// 			padding: 0px;

	// 			// max-height: 0px;
	// 			// overflow: hidden;
	// 			// opacity: 0;
	// 			// padding: 0px;
	// 		}
	// 	}

	// 	&__section-desc {
	// 		font-size: 0.85rem;
	// 		color: $surface-6;
	// 		margin: 0 0 1rem;
	// 	}

	// 	&__empty {
	// 		display: flex;
	// 		align-items: center;
	// 		gap: 1rem;
	// 		justify-content: center;
	// 		padding: 3rem;
	// 		color: $surface-6;
	// 		font-size: 0.9rem;
	// 	}
	// }

	// .stats-grid {
	// 	display: grid;
	// 	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	// 	gap: 1rem;
	// }

	// .entities-grid {
	// 	display: grid;
	// 	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	// 	gap: 1rem;
	// }
</style>
