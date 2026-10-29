<!-- Mensajes del formulario de contacto de la página (los más recientes primero). -->
<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center gap-2">
      <SelectButton v-model="ver" :options="[{ v: 'todos', l: 'Todos' }, { v: 'sinLeer', l: 'Sin leer' }]" option-label="l" option-value="v" :allow-empty="false" />
    </div>
    <Skeleton v-if="!mensajes" height="8rem" />
    <p v-else-if="!visibles.length" class="panel m-0 text-center text-sm text-muted-color">No hay mensajes.</p>
    <article v-for="m in visibles" :key="m.id" class="panel !p-3 flex flex-col gap-2" :class="{ 'border-l-4 !border-l-primary': !m.leido }">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <div class="font-semibold">{{ m.nombre }} <span class="text-xs font-normal uppercase text-muted-color">{{ m.idioma }}</span></div>
          <div class="text-xs text-muted-color">
            <a :href="`mailto:${m.email}`">{{ m.email }}</a><template v-if="m.telefono"> · <a :href="`tel:${m.telefono}`">{{ m.telefono }}</a></template> · {{ fechaHora(m.creado) }}
          </div>
        </div>
        <Button :label="m.leido ? 'Marcar sin leer' : 'Marcar leído'" size="small" text @click="alternar(m)" />
      </div>
      <p class="m-0 whitespace-pre-line text-sm">{{ m.mensaje }}</p>
    </article>
  </div>
</template>

<script setup lang="ts">
import { fetchMensajes, marcarMensaje } from '@/core/pagina-web/api'
import type { MensajeContacto } from '@/core/pagina-web/types'

const emit = defineEmits<{ 'sin-leer': [n: number] }>()
const mensajes = ref<MensajeContacto[] | null>(null)
const ver = ref<'todos' | 'sinLeer'>('todos')
const visibles = computed(() => (mensajes.value ?? []).filter((m) => ver.value === 'todos' || !m.leido))
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

onMounted(async () => {
  const r = await fetchMensajes().catch(() => null)
  mensajes.value = r?.items ?? []
  emit('sin-leer', r?.sinLeer ?? 0)
})

async function alternar(m: MensajeContacto) {
  const r = await marcarMensaje(m.id, !m.leido)
  m.leido = r.leido
  emit('sin-leer', (mensajes.value ?? []).filter((x) => !x.leido).length)
}
</script>
