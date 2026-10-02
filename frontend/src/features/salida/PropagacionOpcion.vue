<!--
  Opción de propagar una operación (editar, anular, eliminar) a las salidas
  futuras idénticas (mismo trayecto, bus, empresa y hora; solo cambia el
  día) que siguen programadas. Avisa de antemano cuántas tienen asientos y
  no se tocarán.
-->
<template>
  <div class="flex flex-col gap-2">
    <Skeleton v-if="!info" height="2.5rem" />
    <template v-else>
      <Message v-if="info.vendidos" severity="warn" :closable="false" class="!text-sm">
        Esta salida tiene {{ info.vendidos }} asiento(s) vendido(s) o apartado(s): no se {{ verbo }}. Reasígnelos o anúlelos en la venta primero.
      </Message>
      <p v-if="!info.identicas" class="m-0 text-xs text-muted-color">No hay salidas futuras idénticas a esta.</p>
      <template v-else>
        <div class="flex items-start gap-2">
          <Checkbox v-model="propagar" binary :input-id="`prop-${salidaId}`" />
          <label :for="`prop-${salidaId}`" class="text-sm">
            {{ capital(verbo) }} también las <b>{{ info.identicas }}</b> salida(s) futuras idénticas
            <span class="text-muted-color">(mismo trayecto, bus y hora, hasta el {{ dia(info.ultima!) }})</span>
          </label>
        </div>
        <p v-if="propagar && info.identicasConAsientos" class="m-0 pl-7 text-xs text-orange-600 dark:text-orange-400">
          {{ info.identicasConAsientos }} de ellas tienen asientos vendidos y quedarán sin cambios.
        </p>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { fetchPropagacion } from '@/core/salida/api'
import type { Propagacion } from '@/core/salida/types'

const props = defineProps<{ salidaId: number; verbo: string }>()
const propagar = defineModel<boolean>({ default: false })
const info = ref<Propagacion | null>(null)

const capital = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
const dia = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(iso))

watch(
  () => props.salidaId,
  async (id) => {
    info.value = null
    propagar.value = false
    info.value = await fetchPropagacion(id).catch(() => ({ salida: { id, fecha: '', ruta: '', bus: null }, vendidos: 0, identicas: 0, identicasConAsientos: 0, ultima: null }))
  },
  { immediate: true },
)
</script>
