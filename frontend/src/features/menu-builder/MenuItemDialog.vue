<!--
  Alta/edición de un ítem navegable: texto, ícono (catálogo Tabler) y ruta
  (`VueRoute`). Las rutas con parámetros (`/lista/:entity`) no se ofrecen:
  el ítem no guarda valores de parámetros, así que no serían navegables.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    :header="draft.id ? 'Editar ítem' : 'Nuevo ítem navegable'"
    :style="{ width: '44rem' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Texto</span>
          <InputText v-model="draft.nombre" autofocus required maxlength="255" />
        </label>
        <label class="flex flex-col gap-1">
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
      </div>
      <div class="flex flex-col gap-1">
        <span class="text-sm font-medium">
          Ícono
          <span
            v-if="draft.icon"
            class="ml-2 inline-flex items-center gap-1 font-normal text-muted-color"
          >
            <icon :name="draft.icon" /> {{ draft.icon }}
          </span>
        </span>
        <IconPicker v-model="draft.icon" height="16rem" />
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

const draft = ref<MenuItemDraft>({ ...props.initial })
watch(
  () => [props.visible, props.initial] as const,
  ([visible]) => {
    if (visible) draft.value = { ...props.initial }
  },
)

const routeOptions = computed(() =>
  props.routes
    .filter((route) => !route.params?.length)
    .map((route) => ({ value: route.id, label: `${route.vueRouteName} — ${route.path ?? ''}` })),
)

const valid = computed(() =>
  Boolean(draft.value.nombre.trim() && draft.value.icon && draft.value.route),
)

function submit() {
  if (valid.value) emit('save', { ...draft.value, nombre: draft.value.nombre.trim() })
}
</script>
