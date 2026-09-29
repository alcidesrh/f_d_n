<!--
  Alta/edición rápida de un cliente desde la venta: NIT (o CF), nombre,
  contacto y, opcionales, documento de identificación y nacionalidad.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    :header="cliente ? 'Editar cliente' : 'Nuevo cliente'"
    :style="{ width: '44rem' }"
    @update:visible="emit('update:visible', $event)"
  >
    <FormKit
      :key="clave"
      v-model="datos"
      type="form"
      :actions="false"
      :incomplete-message="false"
      @submit="guardar"
    >
      <div class="@container">
        <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-2">
          <FormKit
            type="InputText"
            name="nit"
            label="NIT"
            help="Sin guion. CF si es consumidor final."
            validation="required|nit"
            :validation-rules="{ nit: nitValido }"
            :validation-messages="{ nit: 'NIT inválido (dígito verificador).' }"
            fluid
          />
          <FormKit
            type="InputText"
            name="telefono"
            label="Teléfono"
            validation="length:0,15"
            fluid
          />
          <FormKit type="InputText" name="nombre" label="Nombre(s)" validation="required" fluid />
          <FormKit type="InputText" name="apellido" label="Apellido(s)" fluid />
          <FormKit
            type="InputText"
            name="email"
            label="Correo electrónico"
            help="Para enviarle la factura."
            validation="email"
            fluid
          />
          <FormKit
            type="Select"
            name="nacionalidad"
            label="Nacionalidad"
            :options="naciones"
            show-clear
            filter
            fluid
          />
          <FormKit
            type="Select"
            name="tipoDocumento"
            label="Tipo de documento"
            :options="tiposDocumento"
            show-clear
            fluid
          />
          <FormKit type="InputText" name="numeroDocumento" label="Número de documento" fluid />
        </div>
      </div>
      <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" label="Aceptar" :loading="guardando" />
      </div>
    </FormKit>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { crearCliente, editarCliente, errorVenta } from '@/core/venta/api'
import { nitValido as validarNit } from '@/core/venta/nit'
import type { Cliente, ClienteDatos } from '@/core/venta/types'
import { useVentaStore } from './store'

const props = defineProps<{ visible: boolean; cliente: Cliente | null; sugerido?: string }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  guardado: [cliente: Cliente]
}>()

const store = useVentaStore()
const datos = ref<Record<string, unknown>>({})
const guardando = ref(false)
const error = ref('')
const clave = ref(0)

const opciones = (lista: Array<{ id: number; nombre: string }> | undefined) =>
  (lista ?? []).map((o) => ({ label: o.nombre, value: o.id }))
const naciones = computed(() => opciones(store.contexto?.naciones))
const tiposDocumento = computed(() => opciones(store.contexto?.tiposDocumento))

const nitValido = (nodo: { value: unknown }) => validarNit(String(nodo.value ?? ''))

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    error.value = ''
    clave.value++
    const c = props.cliente
    const sugerido = (props.sugerido ?? '').trim()
    const pareceNit = /^[\dkK-]+$/.test(sugerido)
    datos.value = c
      ? { ...c }
      : { nit: pareceNit ? sugerido : 'CF', nombre: pareceNit ? '' : sugerido }
  },
)

async function guardar(valores: Record<string, unknown>) {
  guardando.value = true
  error.value = ''
  try {
    const body = valores as ClienteDatos
    const guardado = props.cliente
      ? await editarCliente(props.cliente.id, body)
      : await crearCliente(body)
    emit('guardado', guardado)
    emit('update:visible', false)
  } catch (e) {
    error.value = errorVenta(e)?.error ?? 'No se pudo guardar el cliente.'
  } finally {
    guardando.value = false
  }
}
</script>
