<!--
  Sección "Saldo" del formulario genérico de Agencia (`formExtensions`):
  saldo, depósitos (con bonificación) y ajustes, y los últimos movimientos.
  El saldo no se edita en el formulario: cada cambio es un movimiento
  (`AgenciaController`, permiso `agencia.acreditar`).
-->
<template>
  <div class="flex flex-col gap-4">
    <Message v-if="agenciaId == null" severity="info" :closable="false">
      Guarde la agencia para registrar depósitos.
    </Message>
    <template v-else-if="estado">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <div class="text-sm text-muted-color">Saldo disponible</div>
          <div class="text-3xl font-semibold tabular-nums">{{ estado.saldo.texto }}</div>
        </div>
        <Tag
          v-if="estado.porcentajeBonificacion"
          severity="info"
          :value="`Bonificación ${estado.porcentajeBonificacion}% sobre depósitos`"
        />
      </div>

      <FormKit
        v-if="estado.puedeAcreditar"
        :key="clave"
        type="form"
        :actions="false"
        @submit="registrar"
      >
        <div class="@container">
          <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-3">
            <FormKit
              type="SelectButton"
              name="tipo"
              label="Movimiento"
              :options="[
                { label: 'Depósito', value: 'deposito' },
                { label: 'Ajuste', value: 'ajuste' },
              ]"
              value="deposito"
            />
            <FormKit
              type="InputNumber"
              name="importe"
              label="Importe (Q)"
              help="En un ajuste, negativo para descontar."
              mode="decimal"
              :min-fraction-digits="2"
              :max-fraction-digits="2"
              validation="required"
              fluid
            />
            <FormKit type="InputText" name="referencia" label="Boleta / referencia" fluid />
          </div>
          <FormKit type="InputText" name="observacion" label="Observación" fluid />
        </div>
        <div class="flex justify-end">
          <Button type="submit" label="Registrar" :loading="guardando" />
        </div>
      </FormKit>

      <DataTable :value="estado.movimientos" size="small" scrollable data-key="id">
        <template #empty
          ><div class="p-3 text-center text-muted-color">Sin movimientos.</div></template
        >
        <Column header="Fecha" class="whitespace-nowrap">
          <template #body="{ data }">{{ fecha(data.fecha) }} {{ hora(data.fecha) }}</template>
        </Column>
        <Column header="Tipo" field="tipo" />
        <Column header="Monto" class="text-right tabular-nums">
          <template #body="{ data }">{{ data.monto.texto }}</template>
        </Column>
        <Column header="Saldo" class="text-right tabular-nums">
          <template #body="{ data }">{{ data.saldo.texto }}</template>
        </Column>
        <Column header="Detalle" style="min-width: 12rem">
          <template #body="{ data }">
            <span v-if="data.venta">Venta {{ data.venta }}</span>
            {{ [data.referencia, data.observacion].filter(Boolean).join(' · ') }}
          </template>
        </Column>
        <Column header="Usuario" field="usuario" />
      </DataTable>
    </template>
    <Skeleton v-else-if="cargando" height="12rem" />
    <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ajustarAgencia, depositarAgencia, errorVenta, fetchSaldoAgencia } from '@/core/venta/api'
import { fecha, hora } from '@/core/venta/modelo'
import type { EstadoAgencia } from '@/core/venta/types'
import { notify } from '@/core/notify'

const props = defineProps<{ entity: string; id: string | number | null }>()

const agenciaId = computed(() =>
  props.id != null && props.id !== '' ? Number(String(props.id).split('/').pop()) : null,
)
const estado = ref<EstadoAgencia | null>(null)
const cargando = ref(false)
const guardando = ref(false)
const error = ref('')
const clave = ref(0)

async function cargar() {
  if (agenciaId.value == null) return
  cargando.value = true
  error.value = ''
  try {
    estado.value = await fetchSaldoAgencia(agenciaId.value)
  } catch (e) {
    error.value = errorVenta(e)?.error ?? 'No se pudo cargar el saldo.'
  } finally {
    cargando.value = false
  }
}
watch(agenciaId, cargar, { immediate: true })

async function registrar(v: {
  tipo: 'deposito' | 'ajuste'
  importe: number
  referencia?: string
  observacion?: string
}) {
  if (agenciaId.value == null) return
  const centavos = Math.round(Number(v.importe) * 100)
  guardando.value = true
  try {
    estado.value =
      v.tipo === 'deposito'
        ? await depositarAgencia(agenciaId.value, {
            importe: centavos,
            referencia: v.referencia,
            observacion: v.observacion,
            bonificacion: true,
          })
        : await ajustarAgencia(agenciaId.value, {
            importe: centavos,
            observacion: v.observacion ?? '',
          })
    notify.success('Movimiento registrado.')
    clave.value++
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? 'No se pudo registrar el movimiento.')
  } finally {
    guardando.value = false
  }
}
</script>
