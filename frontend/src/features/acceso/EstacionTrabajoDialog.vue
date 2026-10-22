<!--
  Estación de trabajo (ADR-039): dónde opera el usuario ahora (vende,
  chequea, recibe). Se abre sola si solo puede operar en estaciones concretas
  y no tiene una válida, o a pedido desde la cabecera. Solo ofrece las de sus
  asignaciones.
-->
<template>
  <Dialog :visible="visible" modal header="Estación de trabajo" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="cerrar">
    <p class="mb-3 text-sm text-muted-color">
      Elija dónde está trabajando: lo que venda, chequee o reciba queda en esa estación. Solo aparecen las de sus asignaciones.
    </p>
    <IconField v-if="acceso.estaciones.length > 8" class="mb-2">
      <InputIcon><icon name="search" /></InputIcon>
      <InputText v-model="filtro" placeholder="Buscar estación" fluid autofocus />
    </IconField>
    <ul class="estaciones" role="listbox" aria-label="Estaciones">
      <li v-for="e in visibles" :key="e.id">
        <button
          type="button"
          role="option"
          class="estacion tap-target"
          :class="{ 'estacion--actual': e.id === acceso.estacionTrabajo?.id }"
          :aria-selected="e.id === acceso.estacionTrabajo?.id"
          :disabled="guardando"
          @click="elegir(e.id)"
        >
          <icon name="location-on-outline" class="text-primary" />
          <span class="min-w-0 flex-1 truncate text-left">
            <span class="font-medium">{{ e.nombre }}</span>
            <span v-if="e.departamento" class="block text-xs text-muted-color">{{ e.departamento }}</span>
          </span>
          <icon v-if="e.id === acceso.estacionTrabajo?.id" name="check" />
        </button>
      </li>
      <li v-if="!visibles.length" class="py-4 text-center text-sm text-muted-color">Ninguna coincide.</li>
    </ul>
    <template #footer>
      <Button label="Más tarde" severity="secondary" text @click="cerrar(false)" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAccesoStore } from '@/core/acceso/store'
import { notify } from '@/core/notify'

const acceso = useAccesoStore()
const filtro = ref('')
const guardando = ref(false)
/** "Más tarde": no se vuelve a abrir sola en esta sesión del navegador. */
const omitida = ref(false)

const visible = computed(() => acceso.eligiendoEstacion || (acceso.debeElegirEstacion && !omitida.value))
const visibles = computed(() => {
  const q = filtro.value.trim().toLocaleLowerCase()
  return q ? acceso.estaciones.filter((e) => `${e.nombre} ${e.departamento ?? ''}`.toLocaleLowerCase().includes(q)) : acceso.estaciones
})

function cerrar(v: boolean) {
  if (v) return
  omitida.value = true
  acceso.eligiendoEstacion = false
}

async function elegir(id: number) {
  guardando.value = true
  try {
    await acceso.elegirEstacion(id)
    notify.success(`Estación de trabajo: ${acceso.estacionTrabajo?.nombre ?? ''}`)
    acceso.eligiendoEstacion = false
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo cambiar la estación.')
  } finally {
    guardando.value = false
  }
}
</script>

<style scoped>
.estaciones {
  display: grid;
  gap: 0.25rem;
  max-height: min(55dvh, 26rem);
  overflow-y: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}
.estacion {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  background: var(--p-surface-0);
  cursor: pointer;
  &:hover:not(:disabled) {
    border-color: var(--p-primary-300);
    background: var(--p-primary-50);
  }
  &:disabled {
    opacity: 0.6;
    cursor: progress;
  }
}
.estacion--actual {
  border-color: var(--p-primary-400);
  background: var(--p-primary-50);
}
</style>
