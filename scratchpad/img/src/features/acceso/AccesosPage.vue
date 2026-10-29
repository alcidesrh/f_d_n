<!--
  Accesos (`/configuracion/accesos`, ADR-039): «¿Quién puede…?» —una
  capacidad, y si se quiere en una empresa y una estación: quién la tiene y
  por qué asignación— y los usuarios activos que no pueden hacer nada (los
  que la migración no pudo ubicar, para revisarlos). Con `iam.ver`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <RouterLink :to="{ name: 'acceso-roles' }" class="text-sm text-primary">Roles y capacidades</RouterLink>
      </template>
    </Toolbar>

    <Tabs v-model:value="pestana">
      <TabList>
        <Tab value="quien">¿Quién puede?</Tab>
        <Tab value="sin">Sin alcance <Badge v-if="sinAlcance.length" :value="sinAlcance.length" severity="warn" class="ml-1" /></Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="quien">
          <form class="filtros" @submit.prevent="buscar">
            <label class="campo">
              <span>Capacidad</span>
              <Select v-model="capacidad" :options="opcionesCapacidad" option-label="nombre" option-value="codigo" option-group-label="area" option-group-children="items" placeholder="Qué puede hacer" filter :filter-fields="['nombre', 'codigo']" fluid />
            </label>
            <label class="campo">
              <span>Empresa (opcional)</span>
              <Select v-model="empresa" :options="catalogo?.empresas ?? []" option-label="nombre" option-value="id" placeholder="En cualquiera" show-clear fluid />
            </label>
            <label class="campo">
              <span>Estación (opcional)</span>
              <Select v-model="estacion" :options="catalogo?.estaciones ?? []" option-label="nombre" option-value="id" placeholder="En cualquiera" filter show-clear fluid />
            </label>
            <Button type="submit" label="Buscar" :loading="buscando" :disabled="!capacidad" class="self-end" />
          </form>

          <Message v-if="resultado && !resultado.length" severity="secondary" :closable="false" class="mt-3">Nadie puede eso ahí.</Message>
          <DataTable v-if="resultado?.length" :value="resultado" size="small" striped-rows class="mt-3" data-key="asignacion" paginator :rows="25" :rows-per-page-options="[25, 50, 100]">
            <Column header="Usuario">
              <template #body="{ data }">
                <RouterLink :to="{ name: 'acceso-usuario', params: { id: data.usuario.id } }" class="text-primary">{{ data.usuario.username }}</RouterLink>
                <div class="text-xs text-muted-color">{{ data.usuario.nombre }}</div>
              </template>
            </Column>
            <Column field="rol" header="Por el rol" />
            <Column header="Alcance">
              <template #body="{ data }">
                <span class="text-sm">{{ data.ambito.agencia ? `Agencia ${data.usuario.agencia ?? data.ambito.agencia}` : describir(data.ambito) }}</span>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <TabPanel value="sin">
          <p class="mb-3 text-sm text-muted-color">Usuarios activos sin ninguna asignación vigente que les conceda algo: sin roles, o con un alcance vacío (la migración no encontró sus empresas o estaciones). Entran, pero no pueden operar.</p>
          <DataTable :value="sinAlcance" size="small" striped-rows :loading="cargandoSin" paginator :rows="25">
            <template #empty>Todos los usuarios activos tienen algo que hacer.</template>
            <Column header="Usuario">
              <template #body="{ data }">
                <RouterLink :to="{ name: 'acceso-usuario', params: { id: data.id } }" class="text-primary">{{ data.username }}</RouterLink>
                <div class="text-xs text-muted-color">{{ data.nombre }}</div>
              </template>
            </Column>
            <Column header="Lugar">
              <template #body="{ data }">{{ data.agencia ? `Agencia ${data.agencia}` : (data.estacion ?? "—") }}</template>
            </Column>
            <Column field="asignaciones" header="Asignaciones" />
          </DataTable>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import * as api from '@/core/acceso/api'
import { describirAmbito } from '@/core/acceso/reglas'
import type { Ambito, CatalogoAcceso, FilaQuienPuede, FilaSinAlcance } from '@/core/acceso/tipos'
import { notify } from '@/core/notify'

defineOptions({ name: 'AccesosPage' })

const pestana = ref('quien')
const catalogo = ref<CatalogoAcceso | null>(null)
const capacidad = ref<string | null>(null)
const empresa = ref<number | null>(null)
const estacion = ref<number | null>(null)
const resultado = ref<FilaQuienPuede[] | null>(null)
const buscando = ref(false)
const sinAlcance = ref<FilaSinAlcance[]>([])
const cargandoSin = ref(false)

const opcionesCapacidad = computed(() => {
  const grupos = new Map<string, { area: string; items: Array<{ codigo: string; nombre: string }> }>()
  for (const c of catalogo.value?.capacidades ?? []) {
    let g = grupos.get(c.area)
    if (!g) grupos.set(c.area, (g = { area: c.area, items: [] }))
    g.items.push({ codigo: c.codigo, nombre: c.nombre })
  }
  return [...grupos.values()]
})

const nombres = computed(() => ({
  empresas: new Map((catalogo.value?.empresas ?? []).map((e) => [e.id, e.nombre])),
  estaciones: new Map((catalogo.value?.estaciones ?? []).map((e) => [e.id, e.nombre])),
}))
const describir = (a: Ambito) => describirAmbito(a, nombres.value)

async function cargarSinAlcance() {
  cargandoSin.value = true
  try {
    sinAlcance.value = await api.fetchSinAlcance()
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo cargar la lista.')
  } finally {
    cargandoSin.value = false
  }
}

void api.fetchCatalogo().then((c) => (catalogo.value = c)).catch((e) => notify.error(e instanceof Error ? e.message : 'No se pudo cargar el catálogo.'))
void cargarSinAlcance()
watch(pestana, (p) => p === 'sin' && void cargarSinAlcance())

async function buscar() {
  if (!capacidad.value) return
  buscando.value = true
  try {
    resultado.value = await api.fetchQuienPuede(capacidad.value, empresa.value, estacion.value)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo consultar.')
  } finally {
    buscando.value = false
  }
}
</script>

<style scoped>
.filtros {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(0, 1fr);
  @container (min-width: 48rem) {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
  }
}
.campo {
  display: grid;
  gap: 0.3rem;
  font-size: 0.8rem;
  color: var(--p-surface-600);
}
</style>
