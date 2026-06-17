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
      <h2 class="dashboard__section-title">Resumen</h2>
      <div class="stats-grid">
        <StatsCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
      </div>
    </section>

    <section class="dashboard__section">
      <h2 class="dashboard__section-title">Gestión de Entidades</h2>
      <p class="dashboard__section-desc">Acceda a las operaciones CRUD de cada entidad del sistema</p>
      <div v-if="entities.length" class="entities-grid">
        <EntityCard
          v-for="entity in entities"
          :key="entity.name"
          :entity="entity"
          :icon="getEntityIcon(entity.name)"
        />
      </div>
      <div v-else class="dashboard__empty">
        <q-spinner-dots color="primary" size="2em" />
        <span>Cargando entidades...</span>
      </div>
    </section>
  </q-page>
</template>

<script setup lang="ts">
import type { Entity } from '@/types/graphql'
import { getEntityIcon } from '@/config/entityIcons'

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

function isEntity(name: string): boolean {
  return !name.endsWith('Connection') &&
    !name.endsWith('Edge') &&
    !name.endsWith('PageInfo') &&
    !name.endsWith('Resource') &&
    !name.endsWith('Payload') &&
    !name.endsWith('PaginationInfo') &&
    !name.endsWith('Input') &&
    !name.startsWith('__')
}

const entities = computed<Entity[]>(() =>
  Object.entries(schemaStore.entities)
    .filter(([name]) => isEntity(name))
    .map(([, entity]) => entity)
)

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
    margin-bottom: 2.5rem;
  }

  &__section-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: $dark;
    margin: 0 0 0.25rem;
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
