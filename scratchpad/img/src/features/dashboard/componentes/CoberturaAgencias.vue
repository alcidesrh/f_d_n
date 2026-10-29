<!--
  Agencias por días de venta que cubre su saldo (saldo / venta diaria de la
  última semana), las más justas primero. Una agencia sin saldo deja de
  vender: menos de 1 día es urgente, menos de 3 hay que avisarle.
-->
<template>
  <div class="flex flex-col gap-2">
    <p v-if="!agencias.length" class="py-4 text-center text-sm text-muted-color">Ninguna agencia vendió esta semana.</p>
    <div v-for="a in visibles" :key="a.id" class="agencia">
      <icon :name="ICONOS[nivel(a)]" :class="CLASES[nivel(a)]" />
      <div class="min-w-0">
        <div class="truncate text-sm font-medium">{{ a.nombre }}</div>
        <div class="text-xs text-muted-color">Saldo {{ dinero(a.saldo, true) }} · vende {{ dinero(a.ventaDiaria, true) }}/día</div>
      </div>
      <div class="text-right">
        <div class="tabular-nums text-sm font-semibold" :class="CLASES[nivel(a)]">{{ a.cobertura === null ? '—' : a.cobertura < 1 ? '< 1 día' : `${a.cobertura.toFixed(a.cobertura < 10 ? 1 : 0)} días` }}</div>
        <div class="text-xs text-muted-color">{{ ETIQUETAS[nivel(a)] }}</div>
      </div>
    </div>
    <RouterLink to="/agencias/saldos" class="self-start text-sm font-medium text-primary">Ver saldos de agencias</RouterLink>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dinero } from '@/core/tablero/formato'
import type { AgenciaCobertura } from '@/core/tablero/types'

const props = defineProps<{ agencias: AgenciaCobertura[] }>()

type Nivel = 'critico' | 'alerta' | 'bien' | 'sinVenta'
const nivel = (a: AgenciaCobertura): Nivel => (a.cobertura === null ? 'sinVenta' : a.cobertura < 1 ? 'critico' : a.cobertura < 3 ? 'alerta' : 'bien')
const ICONOS: Record<Nivel, string> = { critico: 'error', alerta: 'warning', bien: 'check-circle', sinVenta: 'remove' }
const CLASES: Record<Nivel, string> = { critico: 'text-red-700', alerta: 'text-amber-700', bien: 'text-green-700', sinVenta: 'text-muted-color' }
const ETIQUETAS: Record<Nivel, string> = { critico: 'recargar ya', alerta: 'avisar', bien: 'cubierta', sinVenta: 'sin venta reciente' }
const visibles = computed(() => props.agencias.slice(0, 8))
</script>

<style scoped>
.agencia {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 0.625rem;
  align-items: center;
  padding: 0.375rem 0;
  border-bottom: 1px solid var(--p-content-border-color);
}
</style>
