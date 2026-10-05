<!--
  Buscador de clientes: NIT, documento o nombre (3+ caracteres; la consulta
  sale al dejar de escribir). Si no existe, "+" lo da de alta; con uno
  elegido, el lápiz lo edita.
-->
<template>
  <div class="flex items-stretch gap-2">
    <AutoComplete
      :model-value="modelValue"
      :suggestions="sugerencias"
      option-label="label"
      :min-length="3"
      :delay="400"
      force-selection
      :placeholder="placeholder"
      :input-id="inputId"
      class="min-w-0 flex-1"
      input-class="w-full"
      :loading="buscando"
      @complete="buscar($event.query)"
      @update:model-value="elegir"
    >
      <template #option="{ option }">
        <div class="flex flex-col">
          <span class="font-medium">{{ option.nombreCompleto }}</span>
          <span class="text-xs text-muted-color">
            NIT {{ option.nit
            }}<template v-if="option.numeroDocumento">
              · Doc. {{ option.numeroDocumento }}</template
            >
          </span>
        </div>
      </template>
      <template #empty>
        <div class="p-2 text-sm">Sin resultados. Use «+» para registrar al cliente.</div>
      </template>
    </AutoComplete>
    <Button
      title="Nuevo cliente"
      aria-label="Nuevo cliente"
      severity="secondary"
      outlined
      @click="abrir(null)"
    >
      <icon name="add" />
    </Button>
    <Button
      v-if="!compacto"
      title="Editar cliente"
      aria-label="Editar cliente"
      severity="secondary"
      outlined
      :disabled="!modelValue"
      @click="abrir(modelValue)"
    >
      <icon name="edit-outline" />
    </Button>
    <ClienteDialog
      v-model:visible="dialogo"
      :cliente="editando"
      :sugerido="ultimaBusqueda"
      @guardado="elegir"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { buscarClientes } from '@/core/venta/api'
import type { Cliente } from '@/core/venta/types'
import ClienteDialog from './ClienteDialog.vue'

withDefaults(
  defineProps<{
    modelValue: Cliente | null
    placeholder?: string
    inputId?: string
    /** Sin botón de editar (filas de pasajeros). */
    compacto?: boolean
  }>(),
  { placeholder: 'NIT, documento o nombre', inputId: undefined, compacto: false },
)
const emit = defineEmits<{ 'update:modelValue': [cliente: Cliente | null] }>()

const sugerencias = ref<Cliente[]>([])
const buscando = ref(false)
const ultimaBusqueda = ref('')
const dialogo = ref(false)
const editando = ref<Cliente | null>(null)

let consulta = 0
async function buscar(q: string) {
  ultimaBusqueda.value = q
  const n = ++consulta
  buscando.value = true
  try {
    const r = await buscarClientes(q)
    if (n === consulta) sugerencias.value = r
  } catch {
    if (n === consulta) sugerencias.value = []
  } finally {
    if (n === consulta) buscando.value = false
  }
}

function elegir(valor: Cliente | string | null) {
  emit('update:modelValue', valor && typeof valor === 'object' ? valor : null)
}

function abrir(cliente: Cliente | null) {
  editando.value = cliente
  dialogo.value = true
}
</script>
