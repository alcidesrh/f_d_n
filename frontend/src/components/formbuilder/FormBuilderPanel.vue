<template>
  <div class="fb-panel flex flex-col gap-4 p-3">
    <!-- ── Grid ─────────────────────────────────────────────────────── -->

    <section>
      <h3 class="fb-h">Grid</h3>
      <div class="flex items-end gap-2">
        <label class="text-xs text-surface-500">
          Filas
          <InputNumber v-model="gridRows" :min="MIN_ROWS" :max="MAX_ROWS" class="w-full" input-class="w-full" />
        </label>
        <label class="text-xs text-surface-500">
          Columnas
          <InputNumber v-model="gridCols" :min="MIN_COLS" :max="MAX_COLS" class="w-full" input-class="w-full" />
        </label>
      </div>
      <div class="mt-2 flex flex-wrap gap-2">
        <Button
          v-if="!store.root"
          label="Crear grid raíz"
          icon="pi pi-table"
          size="small"
          @click="store.initRoot(gridCols, gridRows)"
        />
        <template v-else>
          <Button
            label="Redimensionar raíz"
            icon="pi pi-arrows-h"
            severity="secondary"
            size="small"
            @click="store.resizeRoot(gridCols, gridRows)"
          />
          <Button
            label="Anidar grid en celda"
            icon="pi pi-tablet"
            severity="secondary"
            size="small"
            :disabled="!canWriteCell"
            @click="onAddNestedGrid"
          />
        </template>
      </div>
    </section>
<Divider/>
    <!-- ── Input ────────────────────────────────────────────────────── -->
    <section>
      <h3 class="fb-h">Input</h3>
      <div class="grid grid-cols-1 gap-2">
        <label class="text-xs text-surface-500">
          Tipo
          <Select
            v-model="draft.inputType"
            :options="BUILDER_INPUT_TYPES"
            class="w-full"
            size="small"
            placeholder="Tipo de input"
          />
        </label>
        <label class="text-xs text-surface-500">
          Name <span class="text-red-400">*</span>
          <InputText v-model="draft.name" class="w-full" size="small" placeholder="nombreDelCampo" />
        </label>
        <label class="text-xs text-surface-500">
          Label
          <InputText v-model="draft.label" class="w-full" size="small" />
        </label>
        <label class="text-xs text-surface-500">
          Placeholder
          <InputText v-model="draft.placeholder" class="w-full" size="small" />
        </label>
        <label class="text-xs text-surface-500">
          Help
          <InputText v-model="draft.help" class="w-full" size="small" />
        </label>
        <label class="text-xs text-surface-500">
          Validación (ej. <code>required|email</code>)
          <InputText v-model="draft.validation" class="w-full" size="small" />
        </label>

        <!-- Extras por tipo -->
        <label v-if="needsOptions" class="text-xs text-surface-500">
          Options (una por línea: <code>Label | valor</code>)
          <TextArea v-model="draft.optionsText" rows="4" class="w-full font-mono text-xs" auto-resize />
        </label>
        <div v-if="draft.inputType === 'InputNumber'" class="flex items-end gap-2">
          <label class="text-xs text-surface-500">
            Min
            <InputNumber v-model="draft.min" class="w-full" input-class="w-full" />
          </label>
          <label class="text-xs text-surface-500">
            Max
            <InputNumber v-model="draft.max" class="w-full" input-class="w-full" />
          </label>
        </div>
        <label v-if="draft.inputType === 'TextArea'" class="text-xs text-surface-500">
          Rows
          <InputNumber v-model="draft.rows" :min="1" :max="20" class="w-full" input-class="w-full" />
        </label>
        <label v-if="draft.inputType === 'InputMask'" class="text-xs text-surface-500">
          Mask (ej. <code>999-999</code>)
          <InputText v-model="draft.mask" class="w-full" size="small" />
        </label>
        <label v-if="draft.inputType === 'DatePicker'" class="text-xs text-surface-500">
          Date format
          <InputText v-model="draft.dateFormat" class="w-full" size="small" />
        </label>
        <ToggleSwitch v-if="draft.inputType === 'Password'" v-model="draft.toggleMask" />

        <!-- Button -->
        <template v-if="draft.inputType === 'Button'">
          <div class="grid grid-cols-2 gap-2">
            <label class="text-xs text-surface-500">
              Severity
              <Select
                v-model="draft.severity"
                :options="SEVERITIES"
                show-clear
                class="w-full"
                size="small"
                placeholder="—"
              />
            </label>
            <label class="text-xs text-surface-500">
              Variant
              <Select
                v-model="draft.variant"
                :options="VARIANTS"
                show-clear
                class="w-full"
                size="small"
                placeholder="—"
              />
            </label>
            <label class="text-xs text-surface-500">
              Size
              <Select
                v-model="draft.size"
                :options="SIZES"
                show-clear
                class="w-full"
                size="small"
                placeholder="—"
              />
            </label>
            <label class="text-xs text-surface-500">
              Icon (clase PrimeIcons)
              <InputText v-model="draft.icon" class="w-full" size="small" placeholder="pi pi-check" />
            </label>
          </div>
        </template>

        <label class="text-xs text-surface-500">
          Props avanzadas (JSON, se mergean al final)
          <TextArea v-model="draft.advancedJson" rows="2" class="w-full font-mono text-xs" auto-resize />
        </label>
        <Message v-if="propsError" severity="error" :closable="false" class="py-1 text-xs">{{ propsError }}</Message>
      </div>

      <div class="mt-2 flex flex-wrap items-center gap-2">
        <Button
          v-if="isEditingField"
          label="Actualizar campo"
          icon="pi pi-sync"
          size="small"
          :disabled="!canWriteCell"
          @click="onSubmitField"
        />
        <Button
          v-else
          label="Asignar a celda"
          icon="pi pi-plus"
          size="small"
          :disabled="!canAssign"
          @click="onSubmitField"
        />
        <span v-if="flash" class="text-xs text-green-600">{{ flash }}</span>
      </div>
    </section>
<Divider/>
    <!-- ── Selección ───────────────────────────────────────────────── -->
    <section>
      <h3 class="fb-h">Selección</h3>
      <p v-if="!store.selectedKey" class="text-xs text-surface-500">Haz click en una celda de la vista previa.</p>
      <template v-else>
        <p class="text-xs text-surface-600">
          Celda <code>{{ store.selectedKey.gridId }}:{{ store.selectedKey.index }}</code> —
          <b>{{ selectionLabel }}</b>
        </p>
        <div class="mt-2 flex gap-2">
          <Button
            label="Eliminar contenido"
            icon="pi pi-trash"
            severity="danger"
            variant="outlined"
            size="small"
            :disabled="!store.selectedNode"
            @click="onRemoveSelected"
          />
        </div>
      </template>
    </section>

    <!-- ── JSON ─────────────────────────────────────────────────────── -->
    <section>
      <h3 class="fb-h">JSON FormKitSchema</h3>
      <pre class="fb-json">{{ store.schemaJson || '// Crea el grid raíz y asigna campos' }}</pre>
      <div class="mt-2 flex flex-wrap gap-2">
        <Button label="Copiar" icon="pi pi-copy" size="small" @click="onCopyJson" />
        <Button label="Descargar .json" icon="pi pi-download" severity="secondary" size="small" @click="onDownloadJson" />
        <span v-if="copied" class="text-xs text-green-600 self-center">Copiado ✓</span>
      </div>

      <details class="mt-3">
        <summary class="cursor-pointer text-xs text-surface-500">Importar borrador</summary>
        <TextArea v-model="importText" rows="4" class="mt-2 w-full font-mono text-xs" auto-resize />
        <Message v-if="importError" severity="error" :closable="false" class="mt-1 py-1 text-xs">{{ importError }}</Message>
        <Button label="Importar" icon="pi pi-upload" severity="secondary" size="small" class="mt-2" @click="onImportDraft" />
      </details>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import {
  BUILDER_INPUT_TYPES,
  MAX_COLS,
  MAX_ROWS,
  MIN_COLS,
  MIN_ROWS,
  OPTION_INPUT_TYPES,
  parseOptionsText,
  type BuilderInputType,
} from '@/utils/formkit/schemaBuilder'

defineOptions({ name: 'FormBuilderPanel' })

const store = useFormBuilderStore()

const SEVERITIES = ['primary', 'secondary', 'success', 'info', 'warn', 'help', 'danger', 'contrast']
const VARIANTS = ['outlined', 'text', 'link']
const SIZES = ['small', 'large']

const gridRows = ref(2)
const gridCols = ref(2)

interface DraftState {
  inputType: BuilderInputType
  name: string
  label: string
  placeholder: string
  help: string
  validation: string
  optionsText: string
  min: number | null
  max: number | null
  rows: number
  mask: string
  dateFormat: string
  toggleMask: boolean
  severity: string
  variant: string
  size: string
  icon: string
  advancedJson: string
}

function emptyDraft(): DraftState {
  return {
    inputType: 'InputText',
    name: '',
    label: '',
    placeholder: '',
    help: '',
    validation: '',
    optionsText: '',
    min: null,
    max: null,
    rows: 3,
    mask: '',
    dateFormat: 'dd/mm/yy',
    toggleMask: false,
    severity: '',
    variant: '',
    size: '',
    icon: '',
    advancedJson: '',
  }
}

const draft = reactive<DraftState>(emptyDraft())
const editingId = ref<string | null>(null)
const propsError = ref('')
const importError = ref('')
const importText = ref('')
const copied = ref(false)
const flash = ref('')

let flashTimer: ReturnType<typeof setTimeout> | undefined

function showFlash(message: string) {
  flash.value = message
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => (flash.value = ''), 2000)
}

const isEditingField = computed(() => {
  const node = store.selectedNode
  return node?.kind === 'field' && node.id === editingId.value
})

/** La celda seleccionada existe, está vacía y no estoy editando ese campo. */
const canAssign = computed(
  () => store.root !== null && store.selectedNode === null && draft.name.trim().length > 0,
)

const canWriteCell = computed(() => store.root !== null && store.selectedKey !== null)

const needsOptions = computed(() => OPTION_INPUT_TYPES.includes(draft.inputType))

const selectionLabel = computed(() => {
  const node = store.selectedNode
  if (!node) return 'vacía'
  if (node.kind === 'grid') return `grid anidado ${node.rows}×${node.cols}`
  return `campo "${String(node.props.name ?? node.props.label ?? node.id)}" (${node.inputType})`
})

watch(
  () => store.selectedNode,
  (node) => {
    if (node?.kind === 'field') loadFieldIntoDraft(node)
    else editingId.value = null
  },
  { immediate: true },
)

function loadFieldIntoDraft(node: { id: string; inputType: BuilderInputType; props: Record<string, unknown> }) {
  editingId.value = node.id
  propsError.value = ''
  const props = node.props
  const options = Array.isArray(props.options)
    ? (props.options as Array<{ label: unknown; value: unknown }>)
    : []
  Object.assign(draft, emptyDraft(), {
    inputType: node.inputType,
    name: String(props.name ?? ''),
    label: String(props.label ?? ''),
    placeholder: String(props.placeholder ?? ''),
    help: String(props.help ?? ''),
    validation: String(props.validation ?? ''),
    optionsText: options.map((option) => `${option.label} | ${option.value}`).join('\n'),
    min: typeof props.min === 'number' ? props.min : null,
    max: typeof props.max === 'number' ? props.max : null,
    rows: typeof props.rows === 'number' ? props.rows : 3,
    mask: String(props.mask ?? ''),
    dateFormat: String(props.dateFormat ?? 'dd/mm/yy'),
    toggleMask: props.toggleMask === true,
    severity: String(props.severity ?? ''),
    variant: String(props.variant ?? ''),
    size: String(props.size ?? ''),
    icon: String(props.icon ?? ''),
    advancedJson: '',
  })
}

function buildProps(): Record<string, unknown> | null {
  propsError.value = ''
  const props: Record<string, unknown> = {}
  const put = (key: string, value: unknown) => {
    if (typeof value === 'string') {
      if (value.trim() !== '') props[key] = value.trim()
    } else if (value !== null && value !== undefined && value !== false) {
      props[key] = value
    }
  }
  put('name', draft.name)
  put('label', draft.label)
  put('placeholder', draft.placeholder)
  put('help', draft.help)
  if (draft.validation.trim() !== '') props.validation = draft.validation.trim()
  if (needsOptions.value && draft.optionsText.trim() !== '') {
    props.options = parseOptionsText(draft.optionsText)
  }
  if (draft.inputType === 'InputNumber') {
    put('min', draft.min)
    put('max', draft.max)
  }
  if (draft.inputType === 'TextArea') put('rows', draft.rows)
  if (draft.inputType === 'InputMask') put('mask', draft.mask)
  if (draft.inputType === 'DatePicker') put('dateFormat', draft.dateFormat)
  if (draft.inputType === 'Password' && draft.toggleMask) props.toggleMask = true
  if (draft.inputType === 'Button') {
    put('severity', draft.severity)
    put('variant', draft.variant)
    put('size', draft.size)
    put('icon', draft.icon)
  }
  if (draft.advancedJson.trim() !== '') {
    try {
      const extra = JSON.parse(draft.advancedJson)
      if (extra === null || typeof extra !== 'object' || Array.isArray(extra)) {
        throw new Error('debe ser un objeto')
      }
      Object.assign(props, extra)
    } catch (cause) {
      propsError.value = `Props avanzadas inválidas: ${cause instanceof Error ? cause.message : String(cause)}`
      return null
    }
  }
  return props
}

function onSubmitField() {
  const props = buildProps()
  if (!props) return
  const inputType = draft.inputType
  const ok = isEditingField.value
    ? store.replaceSelected(inputType, props)
    : store.assignField(inputType, props)
  showFlash(ok ? 'Aplicado ✓' : 'No se pudo aplicar (¿celda ocupada?)')
}

function onAddNestedGrid() {
  showFlash(store.addNestedGrid(gridCols.value, gridRows.value) ? 'Grid anidado creado ✓' : 'La celda debe estar vacía')
}

function onRemoveSelected() {
  store.removeSelected()
}

async function onCopyJson() {
  try {
    await navigator.clipboard.writeText(store.schemaJson)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    importError.value = 'No se pudo acceder al portapapeles'
  }
}

function onDownloadJson() {
  const blob = new Blob([store.schemaJson], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = 'formkit-schema.json'
  anchor.click()
  URL.revokeObjectURL(url)
}

function onImportDraft() {
  importError.value = ''
  try {
    store.importDraft(importText.value)
    showFlash('Borrador importado ✓')
  } catch (cause) {
    importError.value = cause instanceof Error ? cause.message : String(cause)
  }
}
</script>

<style scoped>
.fb-panel {
  overflow-y: auto;
}
.fb-h {
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
  margin-bottom: 0.5rem;
}
.fb-json {
  max-height: 16rem;
  overflow: auto;
  padding: 0.6rem;
  border-radius: 0.5rem;
  background: var(--p-surface-100);
  font-size: 0.65rem;
  line-height: 1.35;
  white-space: pre;
}
code {
  font-family: ui-monospace, monospace;
}
</style>
