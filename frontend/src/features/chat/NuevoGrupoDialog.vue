<!-- Crear un grupo: nombre, modo y miembros (solo personas con las que se puede conversar). -->
<template>
  <Dialog :visible="visible" modal header="Nuevo grupo" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="void chat.cargarContactos()">
    <form class="flex flex-col gap-4" @submit.prevent="crear">
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Nombre</span>
        <InputText v-model="nombre" maxlength="80" placeholder="Ej.: Coordinación Petén" autofocus />
      </label>
      <div class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Quién escribe</span>
        <SelectButton v-model="modo" :options="MODOS" option-label="nombre" option-value="valor" :allow-empty="false" aria-label="Quién escribe" />
        <span class="text-xs text-muted-color">{{ MODOS.find((m) => m.valor === modo)!.detalle }}</span>
      </div>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Miembros</span>
        <MultiSelect v-model="miembros" :options="chat.contactos ?? []" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'lugar']" display="chip" placeholder="Elegir personas" :loading="!chat.contactos" :max-selected-labels="8" class="w-full">
          <template #option="{ option }">
            <span class="flex min-w-0 flex-col">
              <span class="truncate">{{ option.nombre }}</span>
              <span class="truncate text-xs text-muted-color">{{ option.lugar ?? 'Administración' }}</span>
            </span>
          </template>
        </MultiSelect>
        <span class="text-xs text-muted-color">Las agencias no pueden compartir grupo con estaciones.</span>
      </label>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" label="Crear grupo" :loading="creando" :disabled="!nombre.trim() || !miembros.length" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import { useChatStore } from '@/core/chat/store'
import type { ModoGrupo } from '@/core/chat/types'
import { notify } from '@/core/notify'
import { MODOS_GRUPO as MODOS } from './grupo'

defineProps<{ visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [boolean]; creado: [canal: number] }>()

const chat = useChatStore()
const nombre = ref('')
const miembros = ref<number[]>([])
const modo = ref<ModoGrupo>('abierto')
const creando = ref(false)

async function crear() {
  creando.value = true
  try {
    const id = await chat.grupo(nombre.value.trim(), miembros.value, modo.value)
    emit('update:visible', false)
    emit('creado', id)
    nombre.value = ''
    miembros.value = []
    modo.value = 'abierto'
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    creando.value = false
  }
}
</script>
