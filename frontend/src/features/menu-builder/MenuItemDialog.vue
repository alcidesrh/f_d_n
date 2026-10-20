<!--
  Alta/edición de un ítem navegable: texto, ícono (catálogo Material Symbols) y ruta
  (`VueRoute`). Las rutas con parámetros (`/lista/:entity`) no se ofrecen:
  el ítem no guarda valores de parámetros, así que no serían navegables.
  «Solo agrupa» lo deja sin ruta: un grupo despliega a sus hijos y no navega.
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
          <label class="flex flex-col gap-1">
            <span class="text-sm font-medium">Ruta</span>
            <Select
              v-model="draft.route"
              :options="routeOptions"
              option-label="label"
              option-value="value"
              filter
              :disabled="soloAgrupa"
              placeholder="Elegí una ruta"
            />
          </label>
          <label class="flex items-center gap-2 text-sm">
            <Checkbox v-model="soloAgrupa" binary />
            Solo agrupa (sin ruta: despliega sus hijos y no navega)
          </label>
        </div>
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

const maximized = ref(false)
const draft = ref<MenuItemDraft>({ ...props.initial })
/** Un ítem ya guardado sin ruta es un grupo. */
const soloAgrupa = ref(false)
watch(
  () => [props.visible, props.initial] as const,
  ([visible]) => {
    if (!visible) return
    draft.value = { ...props.initial }
    soloAgrupa.value = Boolean(props.initial.id) && props.initial.route === null
  },
)

const routeOptions = computed(() =>
  props.routes
    .filter((route) => !route.params?.length)
    .map((route) => ({ value: route.id, label: `${route.vueRouteName} — ${route.path ?? ''}` })),
)

const valid = computed(() =>
  Boolean(draft.value.nombre.trim() && draft.value.icon && (soloAgrupa.value || draft.value.route)),
)

function submit() {
  if (!valid.value) return
  emit('save', {
    ...draft.value,
    nombre: draft.value.nombre.trim(),
    route: soloAgrupa.value ? null : draft.value.route,
  })
}
</script>
