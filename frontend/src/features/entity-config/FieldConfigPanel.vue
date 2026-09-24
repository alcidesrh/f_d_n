<!--
  Panel de un campo de la configuración de una entidad (ADR-010): las
  propiedades de `CollectionFieldConfig` / `FormFieldConfig` en una sola fila.

  Dos excepciones a la edición: `field` es el nombre de la propiedad de la
  entidad y solo se muestra (cambiarlo rompería el mapeo con Doctrine), y
  `position` es la posición del panel en la lista, que se cambia arrastrando
  el tirador (ver `SortablePanelList`). El panel lee y muta
  la fila directamente en `useEntityConfigStore` (localizada por `fieldKey`,
  estable durante el arrastre, a diferencia del índice).
-->
<template>
  <div v-if="row" class="field-panel" :class="{ 'field-panel--hidden': !row.visible }">
    <span data-drag-handle class="field-panel__handle" title="Arrastrar para cambiar la posición">
      <icon name="grip-vertical" lg />
    </span>

    <span class="field-panel__position" title="Posición (dada por el orden del panel)">
      {{ index + 1 }}
    </span>

    <label class="cell w-36">
      <span class="cell__label" title="Propiedad de la entidad: no es editable">field</span>
      <InputText
        :model-value="row.field"
        size="small"
        class="font-mono cell__readonly"
        readonly
        tabindex="-1"
      />
    </label>

    <label class="cell w-36">
      <span class="cell__label">label</span>
      <InputText
        :model-value="row.label ?? ''"
        size="small"
        :placeholder="row.field"
        @update:model-value="row.label = ($event as string) || null"
      />
    </label>

    <label class="cell w-32">
      <span class="cell__label">kind</span>
      <Select v-model="row.kind" :options="KIND_OPTIONS" size="small" editable placeholder="—" />
    </label>

    <label v-if="variant === 'form'" class="cell w-36">
      <span class="cell__label">groupName</span>
      <InputText
        :model-value="(row as FormFieldRow).groupName ?? ''"
        size="small"
        placeholder="—"
        @update:model-value="(row as FormFieldRow).groupName = ($event as string) || null"
      />
    </label>

    <div class="cell cell--switch">
      <span class="cell__label">visible</span>
      <ToggleSwitch
        :model-value="row.visible"
        @update:model-value="store.setVisible(variant, fieldKey, $event as boolean)"
      />
    </div>

    <template v-if="variant === 'collection'">
      <div class="cell cell--switch">
        <span class="cell__label">sortable</span>
        <ToggleSwitch
          :model-value="Boolean((row as CollectionFieldRow).sortable)"
          @update:model-value="(row as CollectionFieldRow).sortable = $event as boolean"
        />
      </div>
      <div class="cell cell--switch">
        <span class="cell__label">filterable</span>
        <ToggleSwitch
          :model-value="Boolean((row as CollectionFieldRow).filterable)"
          @update:model-value="(row as CollectionFieldRow).filterable = $event as boolean"
        />
      </div>
    </template>

    <label class="cell flex-1 min-w-40">
      <span class="cell__label">
        attrs (JSON)
        <span v-if="attrsInvalid" class="cell__error">· JSON inválido</span>
      </span>
      <InputText
        :model-value="attrsText"
        size="small"
        class="font-mono"
        :invalid="attrsInvalid"
        placeholder='{ "class": "text-right" }'
        @update:model-value="onAttrsInput"
      />
    </label>
  </div>
</template>

<script setup lang="ts">
import { useEntityConfigStore } from './store'
import type { CollectionFieldRow, FormFieldRow } from './store'

defineOptions({ name: 'FieldConfigPanel' })

const props = defineProps<{
  /** IRI de la fila; estable mientras se arrastra (el índice no lo es). */
  fieldKey: string
  /** Posición actual del panel en la lista (0-based). */
  index: number
  variant: 'collection' | 'form'
}>()

/** Valores que produce `CollectionFieldConfig::setData()`; el campo admite otros. */
const KIND_OPTIONS = ['scalar', 'date', 'list']

const store = useEntityConfigStore()

const row = computed<CollectionFieldRow | FormFieldRow | null>(() => {
  const list = props.variant === 'collection' ? store.collectionFields : store.formFields
  return list.find((item) => item.key === props.fieldKey) ?? null
})

const attrsText = ref('')
const attrsInvalid = ref(false)

function serializeAttrs(attrs: Record<string, unknown> | null): string {
  return attrs ? JSON.stringify(attrs) : ''
}

function setInvalid(invalid: boolean) {
  attrsInvalid.value = invalid
  store.setAttrsError(props.fieldKey, invalid)
}

/** Escribe `attrs` solo si el texto es un objeto JSON; si no, marca el panel. */
function onAttrsInput(value: string | undefined) {
  attrsText.value = value ?? ''
  const target = row.value
  if (!target) return

  const raw = attrsText.value.trim()
  if (!raw) {
    target.attrs = null
    setInvalid(false)
    return
  }
  try {
    const parsed: unknown = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      throw new Error('attrs debe ser un objeto JSON')
    }
    target.attrs = parsed as Record<string, unknown>
    setInvalid(false)
  } catch {
    setInvalid(true)
  }
}

// Al cargar otra entidad (o tras guardar) la fila es otro objeto: resincroniza
// el texto con lo que venga del backend.
watch(
  row,
  (value) => {
    attrsText.value = serializeAttrs(value?.attrs ?? null)
    setInvalid(false)
  },
  { immediate: true },
)

onBeforeUnmount(() => store.setAttrsError(props.fieldKey, false))
</script>

<style scoped>
.field-panel {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  height: 100%;
  /* Los paneles no hacen scroll por su cuenta ni encogen sus inputs: el ancho
     que pide el contenido desborda hacia el contenedor scrollable de la lista,
     de modo que todos los paneles se desplazan juntos y alineados. */
  min-width: max-content;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  background: var(--p-surface-0);
}
.field-panel--hidden {
  opacity: 0.6;
}
.field-panel__handle {
  display: flex;
  align-items: center;
  align-self: stretch;
  padding-inline: 0.125rem;
  cursor: grab;
  touch-action: none;
}
.field-panel__handle:active {
  cursor: grabbing;
}
.field-panel__position {
  display: grid;
  place-items: center;
  min-width: 1.75rem;
  height: 1.75rem;
  margin-bottom: 0.3rem;
  border-radius: 9999px;
  background: var(--p-surface-100);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
}
.cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.cell--switch {
  align-items: flex-start;
  padding-bottom: 0.4rem;
}
.cell__label {
  font-size: 0.7rem;
  letter-spacing: 0.02em;
  color: var(--p-text-muted-color);
  white-space: nowrap;
}
.cell__error {
  color: var(--p-red-500);
}
/* `field` es el nombre real de la propiedad de la entidad: se muestra, no se edita. */
.cell__readonly {
  background: var(--p-surface-100);
  color: var(--p-text-muted-color);
  cursor: default;
}
</style>
