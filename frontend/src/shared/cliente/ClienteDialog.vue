<!--
  Alta/edición rápida de un cliente desde la venta: NIT (o CF), nombre,
  contacto y, opcionales, documento de identificación y nacionalidad. En el
  alta, mientras se escribe, muestra los ya registrados que se parecen para
  usar ese en vez de duplicarlo (ADR-068).
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
      :key="formulario"
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
            :help="ayudaNit"
            validation="required|nit"
            :validation-rules="{ nit: nitValido }"
            :validation-messages="{ nit: 'NIT inválido (dígito verificador).' }"
            fluid
          />
          <FormKit
            type="InputText"
            name="telefono"
            label="Teléfono"
            help="Para reconocerlo cuando vuelva."
            validation="length:0,20"
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
          <FormKit
            type="InputText"
            name="numeroDocumento"
            label="Número de documento"
            :validation="esDpi ? 'dpi' : ''"
            :validation-rules="{ dpi: dpiValido }"
            :validation-messages="{ dpi: 'DPI inválido: revise los 13 dígitos del CUI.' }"
            fluid
          />
        </div>
      </div>
      <p v-if="nombreSatDistinto" class="mb-3 flex flex-wrap items-center gap-x-2 text-sm">
        <span>La SAT lo registra como «{{ nombreSat }}».</span>
        <Button label="Usar ese nombre" size="small" link class="p-0" @click="usarNombreSat" />
      </p>
      <div v-if="parecidos.length" class="mb-3 rounded-border border border-surface p-3">
        <p class="mb-2 text-sm font-medium">
          {{ hayFuertes ? 'Este cliente parece estar ya registrado:' : 'Clientes registrados que se parecen:' }}
        </p>
        <ul class="flex flex-col gap-2">
          <li
            v-for="p in parecidos"
            :key="p.cliente.id"
            class="flex flex-wrap items-center gap-2"
          >
            <div class="flex min-w-0 flex-1 flex-col">
              <span class="font-medium">{{ p.cliente.nombreCompleto }}</span>
              <span class="text-xs text-muted-color">
                NIT {{ p.cliente.nit
                }}<template v-if="p.cliente.numeroDocumento">
                  · Doc. {{ p.cliente.numeroDocumento }}</template
                ><template v-if="p.cliente.telefono"> · Tel. {{ p.cliente.telefono }}</template>
              </span>
            </div>
            <Tag :value="p.motivo" :severity="p.fuerte ? 'warn' : 'secondary'" />
            <Button label="Usar este" size="small" outlined @click="usar(p.cliente)" />
          </li>
        </ul>
        <label v-if="hayFuertes" class="mt-3 flex items-center gap-2 text-sm">
          <Checkbox v-model="esOtro" binary input-id="cliente-es-otro" />
          Es otra persona: registrarla de todos modos.
        </label>
      </div>
      <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button
          type="submit"
          label="Aceptar"
          :loading="guardando"
          :disabled="!cliente && hayFuertes && !esOtro"
        />
      </div>
    </FormKit>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  clientesParecidos,
  consultarNit,
  crearCliente,
  editarCliente,
  errorVenta,
} from '@/core/venta/api'
import { cuiValido } from '@/core/venta/cui'
import { normalizarNit, nitValido as validarNit } from '@/core/venta/nit'
import type { Cliente, ClienteDatos, ClienteParecido } from '@/core/venta/types'
import { useCatalogosCliente } from './catalogos'

const props = defineProps<{ visible: boolean; cliente: Cliente | null; sugerido?: string }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  guardado: [cliente: Cliente]
}>()

const catalogos = useCatalogosCliente()
const datos = ref<Record<string, unknown>>({})
const guardando = ref(false)
const error = ref('')
const formulario = ref(0)

const opciones = (lista: Array<{ id: number; nombre: string }> | undefined) =>
  (lista ?? []).map((o) => ({ label: o.nombre, value: o.id }))
const naciones = computed(() => opciones(catalogos.value?.naciones))
const tiposDocumento = computed(() => opciones(catalogos.value?.tiposDocumento))

const nitValido = (nodo: { value: unknown }) => validarNit(String(nodo.value ?? ''))

const esDpi = computed(
  () => catalogos.value?.tiposDocumento.find((t) => t.id === datos.value.tipoDocumento)?.sigla === 'DPI',
)
const dpiValido = (nodo: { value: unknown }) => !String(nodo.value ?? '').trim() || cuiValido(String(nodo.value))

/** Para comparar nombres: sin acentos, signos ni mayúsculas. */
const clave = (texto: string) =>
  texto
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^0-9a-zA-Z]+/g, ' ')
    .trim()
    .toLowerCase()

/**
 * Al escribir un NIT válido se consulta la SAT: confirma que existe y, si el
 * nombre está vacío, lo completa con la razón social registrada.
 */
const ayudaNit = ref('Sin guion. CF si es consumidor final.')
const nombreSat = ref('')
const nombreSatDistinto = computed(() => {
  if (!nombreSat.value) return false
  const escrito = clave(`${datos.value.nombre ?? ''} ${datos.value.apellido ?? ''}`)
  return escrito !== '' && escrito !== clave(nombreSat.value)
})
function usarNombreSat() {
  datos.value = { ...datos.value, nombre: nombreSat.value, apellido: '' }
}
let consultaNit: ReturnType<typeof setTimeout> | undefined
watch(
  () => String(datos.value.nit ?? ''),
  (nit) => {
    clearTimeout(consultaNit)
    ayudaNit.value = 'Sin guion. CF si es consumidor final.'
    nombreSat.value = ''
    const limpio = normalizarNit(nit)
    if (!props.visible || limpio === 'CF' || !validarNit(limpio)) return
    consultaNit = setTimeout(async () => {
      ayudaNit.value = 'Consultando en la SAT…'
      try {
        const r = await consultarNit(limpio)
        if (normalizarNit(String(datos.value.nit ?? '')) !== limpio) return
        ayudaNit.value = `SAT: ${r.nombre}`
        nombreSat.value = r.nombre
        if (!String(datos.value.nombre ?? '').trim())
          datos.value = { ...datos.value, nombre: r.nombre }
      } catch (e) {
        const err = errorVenta(e)
        ayudaNit.value =
          err?.codigo === 'nit_inexistente'
            ? 'La SAT no reconoce este NIT: la factura sería rechazada.'
            : 'No se pudo consultar la SAT (se validará al facturar).'
      }
    }, 500)
  },
)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    error.value = ''
    parecidos.value = []
    esOtro.value = false
    formulario.value++
    const c = props.cliente
    const sugerido = (props.sugerido ?? '').trim()
    const pareceNit = /^[\dkK-]+$/.test(sugerido)
    datos.value = c
      ? { ...c }
      : { nit: pareceNit ? sugerido : 'CF', nombre: pareceNit ? '' : sugerido }
  },
)

/**
 * En el alta, los ya registrados que se parecen a lo escrito (NIT, documento,
 * teléfono, nombre y apellido), al dejar de escribir.
 */
const parecidos = ref<ClienteParecido[]>([])
const hayFuertes = computed(() => parecidos.value.some((p) => p.fuerte))
const esOtro = ref(false)
let consultaParecidos: ReturnType<typeof setTimeout> | undefined
let pedidoParecidos = 0
watch(
  () =>
    ['nit', 'numeroDocumento', 'telefono', 'nombre', 'apellido']
      .map((campo) => String(datos.value[campo] ?? '').trim())
      .join('|'),
  () => {
    clearTimeout(consultaParecidos)
    if (!props.visible || props.cliente) return
    const n = ++pedidoParecidos
    consultaParecidos = setTimeout(async () => {
      try {
        const r = await clientesParecidos(datos.value as Partial<ClienteDatos>)
        if (n === pedidoParecidos) parecidos.value = r
      } catch {
        // Sin la consulta el alta sigue; el backend revisa igual al guardar.
      }
    }, 600)
  },
)

function usar(cliente: Cliente) {
  emit('guardado', cliente)
  emit('update:visible', false)
}

async function guardar(valores: Record<string, unknown>) {
  guardando.value = true
  error.value = ''
  try {
    const body = valores as ClienteDatos
    const guardado = props.cliente
      ? await editarCliente(props.cliente.id, body)
      : await crearCliente({ ...body, registrarAunqueExista: esOtro.value })
    emit('guardado', guardado)
    emit('update:visible', false)
  } catch (e) {
    const err = errorVenta(e)
    if (err?.codigo === 'cliente_duplicado' && err.parecidos) {
      pedidoParecidos++
      parecidos.value = err.parecidos
    }
    error.value = err?.error ?? 'No se pudo guardar el cliente.'
  } finally {
    guardando.value = false
  }
}
</script>
