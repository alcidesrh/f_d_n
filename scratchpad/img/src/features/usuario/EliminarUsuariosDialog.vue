<!--
  Borrado de usuarios desde el listado (`listActions.entityDeleteDialogs`).
  Primero muestra lo que depende de ellos (tablas, cuántos registros y una
  muestra). Cada tabla se desvincula (queda sin usuario) o se eliminan sus
  registros; lo que no tiene dependencias se borra sin preguntar.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    header="Eliminar usuarios"
    class="w-[min(40rem,calc(100vw-1rem))]"
    @update:visible="emit('update:visible', $event)"
    @after-hide="emit('after-hide')"
  >
    <p class="mb-3 text-sm">
      {{ ids.length === 1 ? 'Se eliminará 1 usuario.' : `Se eliminarán ${ids.length} usuarios.` }}
      Esta acción no se puede deshacer.
    </p>

    <div v-if="cargando" class="flex justify-center py-8">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>

    <Message v-else-if="errorCarga" severity="error" :closable="false">{{ errorCarga }}</Message>

    <Message v-else-if="!grupos.length" severity="info" :closable="false">
      Ningún registro depende de estos usuarios. Se eliminarán directamente.
    </Message>

    <div v-else class="flex flex-col gap-4">
      <Message severity="warn" :closable="false">
        Hay registros que apuntan a estos usuarios. Elija qué hacer con cada grupo antes de eliminar.
      </Message>

      <section v-for="grupo in grupos" :key="grupo.tabla" class="flex flex-col gap-2 rounded-lg border border-surface-300 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <span class="font-semibold">{{ grupo.entidad ?? grupo.tabla }}</span>
            <span class="ml-1 text-sm text-muted-color">{{ grupo.total }} {{ grupo.total === 1 ? 'registro' : 'registros' }}</span>
          </div>
          <SelectButton
            :model-value="decisiones[grupo.tabla]"
            :options="opcionesDe(grupo)"
            option-label="label"
            option-value="value"
            :allow-empty="false"
            size="small"
            :aria-label="`Qué hacer con ${grupo.entidad ?? grupo.tabla}`"
            @update:model-value="decisiones[grupo.tabla] = $event"
          />
        </div>

        <ul class="m-0 max-h-48 list-none overflow-y-auto p-0 text-sm">
          <li v-for="registro in grupo.registros" :key="registro.id" class="truncate border-b border-surface-200 py-1 last:border-0">
            {{ registro.etiqueta }}
          </li>
        </ul>
        <p v-if="grupo.total > grupo.registros.length" class="m-0 text-xs text-muted-color">
          y {{ grupo.total - grupo.registros.length }} más
        </p>
        <p v-if="decisiones[grupo.tabla] === 'eliminar'" class="m-0 text-xs font-semibold text-red-600">
          Se eliminarán estos registros de forma permanente.
        </p>
      </section>
    </div>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="eliminando" @click="emit('update:visible', false)" />
      <Button
        :label="ids.length === 1 ? 'Eliminar usuario' : 'Eliminar usuarios'"
        severity="danger"
        :loading="eliminando"
        :disabled="cargando || !!errorCarga"
        @click="eliminar"
      />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  analizarEliminacion,
  eliminarUsuarios,
  type AccionDependencia,
  type GrupoDependencia,
} from '@/core/usuario/eliminacion'
import { notify } from '@/core/notify'

const props = defineProps<{ ids: number[]; visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; 'after-hide': []; listo: [] }>()

const grupos = ref<GrupoDependencia[]>([])
const decisiones = ref<Record<string, AccionDependencia>>({})
const cargando = ref(false)
const eliminando = ref(false)
const errorCarga = ref('')

const ETIQUETAS: Record<AccionDependencia, string> = {
  desvincular: 'Dejar sin usuario',
  eliminar: 'Eliminar registros',
}

const opcionesDe = (grupo: GrupoDependencia) =>
  grupo.acciones.map((value) => ({ value, label: ETIQUETAS[value] }))

/** Por defecto se desvincula cuando se puede (no pierde datos). */
function decidirPorDefecto(lista: GrupoDependencia[]) {
  decisiones.value = Object.fromEntries(
    lista.map((g) => [g.tabla, g.acciones.includes('desvincular') ? 'desvincular' : 'eliminar']),
  )
}

async function analizar() {
  cargando.value = true
  errorCarga.value = ''
  try {
    const r = await analizarEliminacion(props.ids)
    grupos.value = r.grupos
    decidirPorDefecto(r.grupos)
  } catch (e) {
    errorCarga.value = e instanceof Error ? e.message : 'No se pudo analizar los usuarios.'
  } finally {
    cargando.value = false
  }
}

watch(
  () => props.visible,
  (on) => {
    if (on) void analizar()
  },
  { immediate: true },
)

async function eliminar() {
  eliminando.value = true
  try {
    const r = await eliminarUsuarios(props.ids, decisiones.value)
    notify.success(r.usuarios === 1 ? 'Usuario eliminado' : `${r.usuarios} usuarios eliminados`)
    emit('listo')
    emit('update:visible', false)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo eliminar los usuarios.')
  } finally {
    eliminando.value = false
  }
}
</script>
