<template>
  <IconPicker v-if="inline" :model-value="iconName" :height="height" @update:model-value="update" />
  <div v-else class="flex w-full items-center gap-1" :class="context.classes.input">
    <button
      :id="context.id"
      type="button"
      class="p-inputtext flex min-w-0 flex-1 items-center gap-2 text-left"
      :class="{ 'p-invalid': invalid }"
      :name="context.node.name"
      :disabled="disabled"
      aria-haspopup="dialog"
      @click="toggle"
    >
      <icon v-if="iconName" :name="iconName" lg color="text-color" />
      <span class="flex-1 truncate" :class="{ 'text-muted-color': !iconName }">
        {{ iconName || placeholder }}
      </span>
      <icon name="chevron-down" color="text-muted-color" />
    </button>
    <Button
      v-if="showClear && iconName && !disabled"
      type="button"
      text
      rounded
      severity="secondary"
      aria-label="Quitar ícono"
      @click="clear"
    >
      <template #icon><icon name="x" /></template>
    </Button>
    <Popover ref="popover" @hide="blur">
      <div class="w-[min(46rem,calc(100vw-3rem))]">
        <IconPicker :model-value="iconName" :height="height" autofocus @select="onSelect" />
      </div>
    </Popover>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from '@/shared/formkit/useFormKitInput'

defineOptions({ name: 'FkIconPicker' })

/**
 * Buscador de íconos Tabler. El valor es el nombre del ícono (`bus`,
 * `bus-filled`) — el mismo que persiste `Icon.icon` y que pinta `<icon name>`.
 *
 * Attrs: `placeholder`, `inline` (grilla siempre visible, sin popover),
 * `height` (alto de la grilla), `showClear` (por defecto `true`).
 */
const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

const popover = ref<{ toggle: (e: Event) => void; hide: () => void } | null>(null)

const inline = computed(
  () => props.context.attrs.inline === true || props.context.attrs.inline === '',
)
const height = computed(() => (props.context.attrs.height as string | undefined) ?? '22rem')
const placeholder = computed(
  () => (props.context.attrs.placeholder as string | undefined) ?? 'Selecciona un ícono…',
)
const showClear = computed(() => props.context.attrs.showClear !== false)

/**
 * Acepta también una entidad `Icon` hidratada (`{ icon: 'bus', … }`) como
 * valor inicial; lo que se emite siempre es el nombre.
 */
const iconName = computed<string | null>(() => {
  const value = context.value._value as unknown
  // Un IRI (`/api/icons/3`) es una relación que no se pudo hidratar a nombre:
  // se conserva como valor pero no se pinta como ícono.
  if (typeof value === 'string') return value && !value.startsWith('/') ? value : null
  if (value && typeof value === 'object' && 'icon' in value) {
    const icon = (value as { icon?: unknown }).icon
    return typeof icon === 'string' && icon ? icon : null
  }
  return null
})

function toggle(event: Event) {
  popover.value?.toggle(event)
}

function onSelect(name: string) {
  update(name)
  popover.value?.hide()
}

function clear() {
  update(null)
}
</script>
