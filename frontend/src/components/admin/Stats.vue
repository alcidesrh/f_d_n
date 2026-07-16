<template>
	<div v-for="{ color, label, value, icon, percentage, trend } in stats" :key="label" class="stats-card" :style="{ '--accent': color }">
		<div class="stats-card__header">
			<div class="stats-card__icon-wrap">
				<Icon :name="icon" />
			</div>
			<span class="stats-card__value">{{ value }}</span>
		</div>
		<div class="stats-card__footer">
			<span class="stats-card__label">{{ label }}</span>
			<span v-if="trend" class="stats-card__trend" :class="`trend--${trend}`">
				<Icon :name="trendIcon(trend)" />
				{{ percentage }}
			</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Stat {
	label: string
	value: string
	icon: string
	trend: 'up' | 'down' | 'neutral'
	percentage: string
	color: string
}

const trendIcon = (trend) => {
	if (!trend || trend === 'neutral') return 'trending_flat'
	return trend === 'up' ? 'trending_up' : 'trending_down'
}

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
.stats-card {
	background: #fff;
	border: 1px solid $surface-4;
	border-radius: 12px;
	padding: 1.25rem;
	transition:
		box-shadow 0.2s,
		transform 0.2s;

	&:hover {
		box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
		transform: translateY(-2px);
	}

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 0.75rem;
		& > .stats-card__value {
			color: $surface-7;
		}
	}

	&__icon-wrap {
		width: 44px;
		height: 44px;
		border-radius: 10px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: color-mix(in srgb, var(--accent) 12%, transparent);
		color: var(--accent);
		font-size: 1.5rem;
	}

	&__value {
		font-size: 1.75rem;
		font-weight: 700;
		color: $dark;
		line-height: 1.2;
	}

	&__footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	&__label {
		font-size: 0.875rem;
		color: $surface-6;
	}

	&__trend {
		display: inline-flex;
		align-items: center;
		gap: 0.2rem;
		font-size: 0.8rem;
		font-weight: 600;

		&.trend--up {
			color: $positive;
		}
		&.trend--down {
			color: $negative;
		}
		&.trend--neutral {
			color: $surface-6;
		}
	}
}
</style>
