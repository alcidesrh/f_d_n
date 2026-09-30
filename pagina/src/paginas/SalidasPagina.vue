<!-- Salidas del día entre origen y destino, con precio y asientos libres. -->
<template>
  <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6">
    <div class="panel">
      <BuscadorViaje
        :key="clave"
        :origen-inicial="origen"
        :destino-inicial="destino"
        :fecha-inicial="dia"
        @buscar="buscar"
      />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="m-0 text-xl font-semibold">
        Salidas del {{ dia ? dia.toLocaleDateString('es-GT', { weekday: 'long', day: 'numeric', month: 'long' }) : '' }}
      </h1>
      <div class="flex gap-2">
        <Button label="Día anterior" size="small" severity="secondary" outlined :disabled="esHoy" @click="moverDia(-1)" />
        <Button label="Día siguiente" size="small" severity="secondary" outlined @click="moverDia(1)" />
      </div>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <template v-else-if="cargando">
      <Skeleton v-for="n in 3" :key="n" height="6rem" border-radius="1rem" />
    </template>
    <div v-else-if="!salidas.length" class="panel text-center text-muted-color">
      No hay salidas a la venta en línea para esa fecha. Pruebe otro día o compre en la estación.
    </div>
    <ul v-else class="m-0 flex list-none flex-col gap-3 p-0">
      <li v-for="s in salidas" :key="s.id" class="panel grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-center">
        <div class="flex flex-col gap-1">
          <div class="flex flex-wrap items-baseline gap-x-3">
            <span class="text-2xl font-semibold tabular-nums">{{ hora(s.salida ?? s.salidaInicio) }}</span>
            <span v-if="s.llegada" class="text-muted-color">→ llega aprox. {{ hora(s.llegada) }}</span>
          </div>
          <div class="text-sm">{{ s.empresa }} · ruta {{ s.ruta }}</div>
          <div v-if="!s.salida" class="text-xs text-muted-color">Hora de salida del inicio de la ruta.</div>
          <div class="flex flex-wrap gap-2 pt-1">
            <Tag v-for="c in s.clases" :key="c.clase" severity="secondary" :value="`Clase ${c.clase}: ${c.precio.texto}`" />
            <Tag :severity="s.disponibles > 5 ? 'success' : 'warn'" :value="`${s.disponibles} asientos libres`" />
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 md:flex-col md:items-end">
          <div class="text-right">
            <div class="text-xs text-muted-color">desde</div>
            <div class="text-xl font-bold text-primary">{{ s.desde.texto }}</div>
          </div>
          <Button label="Elegir asientos" :disabled="s.disponibles <= 0" @click="elegir(s)" />
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '@/api'
import BuscadorViaje from '@/componentes/BuscadorViaje.vue'
import { desdeISO, diaISO, hora } from '@/modelo'
import type { Salida } from '@/tipos'

const route = useRoute()
const router = useRouter()
const salidas = ref<Salida[]>([])
const cargando = ref(false)
const error = ref('')
const clave = ref(0)

const origen = computed(() => Number(route.query.origen) || null)
const destino = computed(() => Number(route.query.destino) || null)
const dia = computed(() => desdeISO(String(route.query.fecha ?? '')))
const esHoy = computed(() => !!dia.value && diaISO(dia.value) <= diaISO(new Date()))

watch(
  () => route.query,
  async () => {
    error.value = ''
    if (!origen.value || !destino.value || !dia.value) {
      salidas.value = []
      return
    }
    cargando.value = true
    try {
      salidas.value = await api.salidas(origen.value, destino.value, diaISO(dia.value))
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e)
    } finally {
      cargando.value = false
    }
  },
  { immediate: true },
)

function buscar(b: { origen: number; destino: number; fecha: Date }) {
  void router.push({ query: { origen: b.origen, destino: b.destino, fecha: diaISO(b.fecha) } })
}

function moverDia(delta: number) {
  if (!dia.value) return
  const d = new Date(dia.value)
  d.setDate(d.getDate() + delta)
  clave.value++
  void router.push({ query: { ...route.query, fecha: diaISO(d) } })
}

function elegir(s: Salida) {
  void router.push({ name: 'salida', params: { id: s.id }, query: { trayecto: s.trayecto } })
}
</script>
