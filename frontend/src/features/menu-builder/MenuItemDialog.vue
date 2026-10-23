<!--
  Alta/edición de un ítem navegable: texto, ícono (catálogo Material Symbols) y destino.
  El destino es una ruta (`VueRoute`), un enlace escrito a mano (`/salidas/chequeo`) o
  «Solo agrupa», que deja el ítem sin destino: despliega a sus hijos y no navega.
  Las rutas con parámetros (`/lista/:entity`) no se ofrecen: el ítem no guarda valores
  de parámetros, así que no serían navegables.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    maximizable
    :header="draft.id ? 'Editar ítem' : 'Nuevo ítem navegable'"
    :style="{ width: '44rem' }"
    :pt="{ content: { class: maximized ? 'flex flex-col' : '' } }"
    @update:visible="emit('update:visible', $event)"
    @maximize="maximized = true"
    @unmaximize="maximized = false"
  >
    <form class="flex flex-col gap-4" :class="{ 'min-h-0 flex-1': maximized }" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Texto</span>
          <InputText v-model="draft.nombre" autofocus required maxlength="255" />
        </label>
        <div class="flex flex-col gap-1">
          <span class="text-sm font-medium">Destino</span>
          <SelectButton
            v-model="tipo"
            :options="TIPOS"
            option-label="label"
            option-value="value"
            :allow-empty="false"
          />
        </div>
        <label v-if="tipo === 'ruta'" class="flex flex-col gap-1 sm:col-span-2">
          <span class="text-sm font-medium">Ruta</span>
          <Select
            v-model="draft.route"
            :options="routeOptions"
            option-label="label"
            option-value="value"
            filter
            placeholder="Elegí una ruta"
          />
        </label>
        <label v-else-if="tipo === 'enlace'" class="flex flex-col gap-1 sm:col-span-2">
          <span class="text-sm font-medium">Enlace</span>
          <InputText
            v-model="draft.path"
            required
            maxlength="255"
            placeholder="/salidas/chequeo"
            :invalid="draft.path !== null && draft.path !== '' && !enlaceValido"
          />
          <small class="text-muted-color">
            Empieza con «/», sin espacios. Se navega tal cual; la pantalla exige su capacidad igual que cualquier otra.
          </small>
        </label>
        <p v-else class="text-sm text-muted-color sm:col-span-2">
          Solo agrupa: despliega sus hijos y no navega.
        </p>
      </div>
      <div class="flex flex-col gap-1" :class="{ 'min-h-0 flex-1': maximized }">
        <span class="text-sm font-medium">
          Ícono
          <span
            v-if="draft.icon"
            class="ml-2 inline-flex items-center gap-1 font-normal text-muted-color"
          >
            <icon :name="draft.icon" /> {{ draft.icon }}
          </span>
        </span>
        <IconPicker
          v-model="draft.icon"
          :height="maximized ? 'fill' : '16rem'"
          :class="{ 'min-h-0 flex-1': maximized }"
        />
      </div>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" label="Guardar" :disabled="!valid" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import type { VueRouteDto } from './api'
import type { MenuItemDraft } from './store'

const props = defineProps<{
  visible: boolean
  initial: MenuItemDraft
  routes: VueRouteDto[]
  saving?: boolean
}>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; save: [draft: MenuItemDraft] }>()

type Tipo = 'ruta' | 'enlace' | 'grupo'

const TIPOS: Array<{ label: string; value: Tipo }> = [
  { label: 'Ruta', value: 'ruta' },
  { label: 'Enlace', value: 'enlace' },
  { label: 'Solo agrupa', value: 'grupo' },
]
/** Igual que el backend (`MenuItem::$path`): una barra inicial y sin espacios. */
const ENLACE = /^\/\S*$/

const maximized = ref(false)
const draft = ref<MenuItemDraft>({ ...props.initial })
const tipo = ref<Tipo>('ruta')

/** Un ítem ya guardado sin destino es un grupo; uno nuevo arranca como ruta. */
function tipoDe(item: MenuItemDraft): Tipo {
  if (item.path !== null) return 'enlace'
  if (item.route !== null || !item.id) return 'ruta'
  return 'grupo'
}

watch(
  () => [props.visible, props.initial] as const,
  ([visible]) => {
    if (!visible) return
    draft.value = { ...props.initial }
    tipo.value = tipoDe(props.initial)
  },
)

const routeOptions = computed(() =>
  props.routes
    .filter((route) => !route.params?.length)
    .map((route) => ({ value: route.id, label: `${route.vueRouteName} — ${route.path ?? ''}` })),
)

const enlaceValido = computed(() => ENLACE.test((draft.value.path ?? '').trim()))

const valid = computed(() => {
  if (!draft.value.nombre.trim() || !draft.value.icon) return false
  if (tipo.value === 'ruta') return Boolean(draft.value.route)
  if (tipo.value === 'enlace') return enlaceValido.value
  return true
})

function submit() {
  if (!valid.value) return
  emit('save', {
    ...draft.value,
    nombre: draft.value.nombre.trim(),
    route: tipo.value === 'ruta' ? draft.value.route : null,
    path: tipo.value === 'enlace' ? (draft.value.path ?? '').trim() : null,
  })
}
</script>
