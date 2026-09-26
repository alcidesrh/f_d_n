<!--
  Sección "Croquis" del formulario genérico de Bus (`formExtensions`): carga
  el croquis del bus, lo edita con `CroquisEditor` y lo guarda después de los
  datos del bus (al crear, con el id recién asignado).
-->
<template>
  <div>
    <div v-if="cargando" class="flex flex-col gap-3">
      <Skeleton height="2.6rem" />
      <Skeleton height="26rem" />
    </div>
    <Message v-else-if="error" severity="error" :closable="false">
      {{ error }}
      <Button label="Reintentar" size="small" text @click="cargar" />
    </Message>
    <template v-else>
      <Message v-if="recuperado" severity="warn" class="mb-3" :closable="false">
        Este croquis no llegó a guardarse. Revisalo y guardá de nuevo.
      </Message>
      <CroquisEditor ref="editor" :inicial="inicial" :bus-id="busId" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue'
import { busIdNumerico, fetchCroquis, saveCroquis } from '@/core/croquis/api'
import { tieneErrores, type Problema } from '@/core/croquis/model'
import type { ElementoCroquis } from '@/core/croquis/types'
import { useEntityFormExtension } from '@/core/entities/formExtension'
import CroquisEditor from './CroquisEditor.vue'
import { recuperarPendiente, guardarPendiente } from './pendientes'

const props = defineProps<{ entity: string; id: string | number | null }>()

const busId = computed(() => (props.id != null && props.id !== '' ? busIdNumerico(props.id) : null))
const inicial = shallowRef<ElementoCroquis[]>([])
const cargando = ref(false)
const error = ref('')
const recuperado = ref(false)
const editor = ref<{
  croquis: () => ElementoCroquis[]
  problemas: Problema[]
} | null>(null)

async function cargar() {
  error.value = ''
  recuperado.value = false
  const pendiente = busId.value != null ? recuperarPendiente(busId.value) : null
  if (pendiente) {
    inicial.value = pendiente
    recuperado.value = true
    return
  }
  if (busId.value == null) {
    inicial.value = []
    return
  }
  cargando.value = true
  try {
    inicial.value = await fetchCroquis(busId.value)
  } catch (cause) {
    error.value = `No se pudo cargar el croquis: ${cause instanceof Error ? cause.message : String(cause)}`
  } finally {
    cargando.value = false
  }
}

watch(busId, () => void cargar(), { immediate: true })

useEntityFormExtension({
  validate() {
    if (error.value) return 'El croquis no se cargó: no se puede guardar el bus sin él.'
    if (editor.value && tieneErrores(editor.value.problemas))
      return 'El croquis tiene errores (marcados en rojo): corregilos antes de guardar.'
    return null
  },
  async afterSave(item) {
    if (!editor.value || item.id == null) return
    const id = busIdNumerico(item.id as string | number)
    const elementos = editor.value.croquis()
    try {
      inicial.value = await saveCroquis(id, elementos)
      recuperado.value = false
    } catch (cause) {
      const motivo = cause instanceof Error ? cause.message : String(cause)
      if (busId.value == null) {
        // Bus recién creado: el formulario pasa a su edición; que el croquis no se pierda.
        guardarPendiente(id, elementos)
        throw new Error(`El bus se creó, pero su croquis no se guardó: ${motivo}`)
      }
      throw new Error(`No se guardó el croquis: ${motivo}`)
    }
  },
})
</script>
