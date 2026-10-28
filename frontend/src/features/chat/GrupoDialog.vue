<!--
  Información y edición de un grupo. Su dueño cambia el nombre, el modo
  (abierto o dirigido), los miembros, quiénes escriben en uno dirigido y los
  grupos abiertos que son miembros; se guarda todo junto. Los demás lo ven y
  pueden salir del grupo. Los que llegan por un grupo miembro se cuentan
  con ese grupo, no uno por uno.
-->
<template>
  <Dialog :visible="visible" modal :header="administro ? 'Editar grupo' : 'Información del grupo'" class="w-[min(32rem,calc(100vw-1rem))]" :pt="{ content: { class: '!pb-2' } }" @update:visible="emit('update:visible', $event)" @show="preparar">
    <div v-if="canal" class="flex flex-col gap-4">
      <label v-if="administro" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Nombre</span>
        <InputText v-model="nombre" maxlength="80" />
      </label>
      <div v-else class="flex items-center gap-3">
        <ChatAvatar :id="canal.id" :nombre="canal.nombre" grupo :foto="canal.foto" />
        <div class="min-w-0">
          <div class="truncate font-semibold">{{ canal.nombre }}</div>
          <div class="text-xs text-muted-color">{{ MODOS.find((m) => m.valor === canal!.modo)?.detalle }}</div>
        </div>
      </div>

      <div v-if="administro" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Quién escribe</span>
        <SelectButton v-model="modo" :options="MODOS" option-label="nombre" option-value="valor" :option-disabled="(m: (typeof MODOS)[number]) => m.valor === 'dirigido' && canal!.esSubgrupo" :allow-empty="false" aria-label="Quién escribe" />
        <span class="text-xs text-muted-color">
          {{ MODOS.find((m) => m.valor === modo)!.detalle }}
          <template v-if="canal.esSubgrupo"> Es miembro de otro grupo: solo puede ser abierto.</template>
        </span>
      </div>

      <section class="flex flex-col gap-1">
        <h3 class="seccion">Personas · {{ personas.length }}</h3>
        <div class="filas">
          <div v-for="p in personas" :key="p.id" class="fila">
            <ChatAvatar :id="p.id" :nombre="p.nombre" :ambito="p.ambito" :foto="p.foto" tamano="sm" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm">{{ p.id === chat.yo?.id ? `${p.nombre} (tú)` : p.nombre }}</span>
              <span class="block truncate text-xs text-muted-color">{{ p.lugar ?? 'Administración' }}</span>
            </span>
            <Tag v-if="p.id === canal.dueno" value="Dueño" severity="secondary" />
            <label v-else-if="modo === 'dirigido' && (administro || p.emisor)" class="escribe">
              <span>Escribe</span>
              <ToggleSwitch v-model="p.emisor" :disabled="!administro" />
            </label>
            <button v-if="administro && p.id !== canal.dueno && p.id !== chat.yo?.id" type="button" class="quitar tap-target" :aria-label="`Quitar a ${p.nombre}`" v-tooltip.left="'Quitar del grupo'" @click="quitarPersona(p.id)">
              <icon name="person-remove-outline" size="1.1rem" color="text-current" />
            </button>
          </div>
        </div>
        <MultiSelect v-if="administro" v-model="nuevas" :options="disponibles" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'lugar']" display="chip" placeholder="Agregar personas" :loading="!chat.contactos" :max-selected-labels="6" class="mt-1 w-full">
          <template #option="{ option }">
            <span class="flex min-w-0 flex-col">
              <span class="truncate">{{ option.nombre }}</span>
              <span class="truncate text-xs text-muted-color">{{ option.lugar ?? 'Administración' }}</span>
            </span>
          </template>
        </MultiSelect>
      </section>

      <section v-if="grupos.length || (administro && !canal.esSubgrupo)" class="flex flex-col gap-1">
        <h3 class="seccion">Grupos miembros · {{ grupos.length }}</h3>
        <div class="filas">
          <div v-for="g in grupos" :key="g.id" class="fila">
            <ChatAvatar :id="g.id" :nombre="g.nombre" grupo :foto="g.foto" tamano="sm" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm">{{ g.nombre }}</span>
              <span class="block truncate text-xs text-muted-color">{{ porGrupo(g.id) }}</span>
            </span>
            <label v-if="modo === 'dirigido' && (administro || g.emisor)" class="escribe">
              <span>Escriben</span>
              <ToggleSwitch v-model="g.emisor" :disabled="!administro" />
            </label>
            <button v-if="administro" type="button" class="quitar tap-target" :aria-label="`Quitar el grupo ${g.nombre}`" v-tooltip.left="'Quitar del grupo'" @click="grupos = grupos.filter((x) => x.id !== g.id)">
              <icon name="close" size="1.1rem" color="text-current" />
            </button>
          </div>
          <p v-if="!grupos.length" class="px-2 py-1 text-xs text-muted-color">Un grupo abierto puede ser miembro: todos los suyos reciben, y su nombre se ve junto al de quien escribe.</p>
        </div>
        <Select v-if="administro && !canal.esSubgrupo" :model-value="null" :options="gruposDisponibles" option-label="nombre" option-value="id" placeholder="Agregar un grupo" filter empty-message="Sin grupos abiertos para agregar" class="mt-1 w-full" @update:model-value="agregarGrupo" />
      </section>
    </div>

    <template #footer>
      <Button v-if="puedeSalir" label="Salir del grupo" severity="danger" text class="mr-auto" :loading="saliendo" @click="salir">
        <template #icon><icon name="logout" size="1rem" color="text-current" class="mr-1.5" /></template>
      </Button>
      <Button :label="administro ? 'Cancelar' : 'Cerrar'" severity="secondary" text @click="emit('update:visible', false)" />
      <Button v-if="administro" label="Guardar" :loading="guardando" :disabled="!nombre.trim()" @click="guardar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { useChatStore } from '@/core/chat/store'
import type { Miembro, ModoGrupo, Subgrupo } from '@/core/chat/types'
import { notify } from '@/core/notify'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import { MODOS_GRUPO as MODOS } from './grupo'

const props = defineProps<{ visible: boolean; canalId: number }>()
const emit = defineEmits<{ 'update:visible': [boolean]; salio: [] }>()

const chat = useChatStore()
const canal = computed(() => chat.canal(props.canalId))
const administro = computed(() => canal.value?.administro ?? false)

const nombre = ref('')
const modo = ref<ModoGrupo>('abierto')
/** Miembros por sí mismos (los que llegan por un grupo van con su grupo). */
const directos = ref<Miembro[]>([])
const grupos = ref<Subgrupo[]>([])
/** Personas elegidas para agregar. */
const nuevas = ref<number[]>([])
const guardando = ref(false)
const saliendo = ref(false)

const personas = directos
// Las elegidas pasan a la lista (ahí se les puede dar permiso de escribir).
watch(nuevas, (ids) => {
  if (!ids.length) return
  const elegidas = (chat.contactos ?? []).filter((p) => ids.includes(p.id) && !directos.value.some((d) => d.id === p.id))
  directos.value = [...directos.value, ...elegidas.map((p) => reactive<Miembro>({ ...p, via: null, emisor: false }))]
  nuevas.value = []
})
const disponibles = computed(() => (chat.contactos ?? []).filter((p) => !directos.value.some((d) => d.id === p.id)))

/** Grupos abiertos míos que pueden ser miembros: sin grupos miembros propios y que no sean este. */
const gruposDisponibles = computed(() =>
  chat.canales.filter((c) => c.tipo === 'grupo' && c.id !== props.canalId && c.modo === 'abierto' && !c.subgrupos.length && !grupos.value.some((g) => g.id === c.id)),
)

const puedeSalir = computed(() => {
  const c = canal.value
  const yo = c?.miembros.find((m) => m.id === chat.yo?.id)
  return !!c && c.dueno !== chat.yo?.id && yo?.via === null
})

function porGrupo(id: number) {
  const n = canal.value?.miembros.filter((m) => m.via === id).length ?? 0
  return n === 1 ? '1 persona llega por este grupo' : `${n} personas llegan por este grupo`
}

function preparar() {
  const c = canal.value
  if (!c) return
  nombre.value = c.nombre
  modo.value = c.modo ?? 'abierto'
  directos.value = c.miembros.filter((m) => m.via === null).map((m) => reactive({ ...m }))
  grupos.value = c.subgrupos.map((g) => reactive({ ...g }))
  nuevas.value = []
  if (c.administro) void chat.cargarContactos()
}

function quitarPersona(id: number) {
  directos.value = directos.value.filter((p) => p.id !== id)
}

function agregarGrupo(id: number | null) {
  const c = chat.canal(id ?? 0)
  if (c) grupos.value = [...grupos.value, reactive({ id: c.id, nombre: c.nombre, foto: c.foto ?? null, emisor: false })]
}

async function guardar() {
  guardando.value = true
  try {
    await chat.editarGrupo(props.canalId, {
      nombre: nombre.value.trim(),
      modo: modo.value,
      miembros: personas.value.map((p) => p.id),
      emisores: personas.value.filter((p) => p.emisor).map((p) => p.id),
      subgrupos: grupos.value.map((g) => ({ id: g.id, emisor: g.emisor })),
    })
    notify.success('Grupo actualizado.')
    emit('update:visible', false)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    guardando.value = false
  }
}

async function salir() {
  saliendo.value = true
  try {
    const sigue = await chat.salirDeGrupo(props.canalId)
    emit('update:visible', false)
    if (sigue) notify.info('Sigue recibiendo los mensajes del grupo por un grupo del que es miembro.')
    else {
      notify.success('Salió del grupo.')
      emit('salio')
    }
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    saliendo.value = false
  }
}
</script>

<style scoped>
.seccion {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-surface-600);
}
.filas {
  max-height: min(16rem, 34dvh);
  overflow-y: auto;
  margin: 0 -0.5rem;
}
.fila {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.4rem 0.5rem;
  border-radius: 0.6rem;
  &:hover {
    background: var(--p-surface-50);
  }
}
.escribe {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.quitar {
  display: grid;
  place-items: center;
  border-radius: 50%;
  color: var(--p-surface-500);
  &:hover {
    color: var(--c-danger);
    background: var(--p-surface-100);
  }
}
</style>
