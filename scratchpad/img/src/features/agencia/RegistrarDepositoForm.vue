<!--
  Registrar un depósito de agencia (el "Crear Depósito de Agencia" del
  legado, ADR-034): agencia, fecha, número de boleta, importe, aplica bono y
  observación. El saldo no cambia hasta que se acredite. Un usuario de agencia
  la tiene fija y no decide el bono. Lo usan la página y el diálogo del listado.
-->
<template>
  <form class="@container flex flex-col gap-4" novalidate @submit.prevent="enviar">
    <div class="campos">
      <div class="campo campo-ancho">
        <label for="dep-agencia">Agencia</label>
        <Select
          v-model="b.agencia"
          input-id="dep-agencia"
          :options="opciones.agencias"
          option-label="nombre"
          option-value="id"
          :disabled="opciones.agenciaPropia !== null || enviando"
          filter
          auto-filter-focus
          placeholder="Seleccione la agencia"
          :invalid="!!mostrar.agencia"
          fluid
        >
          <template #option="{ option }">
            <span :class="{ 'text-muted-color': !option.activo }">{{ option.nombre }}</span>
            <Tag v-if="!option.activo" value="Bloqueada" severity="secondary" class="ml-2 !text-xs" />
          </template>
        </Select>
        <small v-if="mostrar.agencia" class="error">{{ mostrar.agencia }}</small>
      </div>
      <div class="campo">
        <label for="dep-fecha">Fecha del depósito</label>
        <DatePicker v-model="fecha" input-id="dep-fecha" date-format="dd/mm/yy" :max-date="hoyFecha" show-icon :disabled="enviando" :invalid="!!mostrar.fecha" fluid />
        <small v-if="mostrar.fecha" class="error">{{ mostrar.fecha }}</small>
      </div>
      <div class="campo">
        <label for="dep-boleta">Número de boleta</label>
        <InputText id="dep-boleta" v-model="b.numeroBoleta" :maxlength="MAX_BOLETA" autocomplete="off" :disabled="enviando" :invalid="!!mostrar.numeroBoleta" fluid />
        <small v-if="mostrar.numeroBoleta" class="error">{{ mostrar.numeroBoleta }}</small>
      </div>
      <div class="campo">
        <label for="dep-importe">Importe ({{ moneda }})</label>
        <InputNumber v-model="b.importe" input-id="dep-importe" mode="decimal" :min-fraction-digits="2" :max-fraction-digits="2" :min="0" :disabled="enviando" :invalid="!!mostrar.importe" fluid />
        <small v-if="mostrar.importe" class="error">{{ mostrar.importe }}</small>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
      <label class="flex items-center gap-2" :class="{ 'opacity-70': !opciones.puede.elegirBono }">
        <Checkbox v-model="b.aplicaBono" binary :disabled="!opciones.puede.elegirBono || enviando" />
        <span>Aplica bono</span>
      </label>
      <span v-if="agencia" class="text-sm text-muted-color">
        <template v-if="agencia.porcentajeBonificacion && Number(agencia.porcentajeBonificacion) > 0">
          {{ agencia.porcentajeBonificacion }}% de bonificación<template v-if="b.aplicaBono && bono > 0">: <strong class="tabular-nums">{{ quetzales(bono, moneda) }}</strong> al acreditarse</template>
        </template>
        <template v-else>La agencia no tiene bonificación.</template>
      </span>
    </div>

    <div class="campo">
      <label for="dep-observacion">Observación <span class="font-normal">(opcional)</span></label>
      <Textarea id="dep-observacion" v-model="b.observacion" rows="2" auto-resize maxlength="200" :disabled="enviando" fluid />
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <div class="flex flex-wrap justify-end gap-2">
      <slot name="acciones" />
      <Button type="submit" label="Registrar depósito" :loading="enviando">
        <template #icon><icon name="savings-outline" class="mr-1" /></template>
      </Button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { registrarDeposito } from '@/core/agencia/api'
import { aNuevoDeposito, bonoPrevisto, erroresDeposito, MAX_BOLETA, quetzales, type BorradorDeposito } from '@/core/agencia/depositos'
import type { DepositoFila, OpcionesDepositos } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { deDia } from '@/core/salida/filtro'
import { diaISO } from '@/core/venta/modelo'

const props = defineProps<{ opciones: OpcionesDepositos; agenciaInicial?: number | null }>()
const emit = defineEmits<{ registrado: [deposito: DepositoFila] }>()

const nuevo = (): BorradorDeposito => ({
  agencia: props.opciones.agenciaPropia ?? props.agenciaInicial ?? null,
  fecha: props.opciones.hoy,
  importe: null,
  numeroBoleta: '',
  aplicaBono: true,
  observacion: '',
})
const b = reactive<BorradorDeposito>(nuevo())
const enviando = ref(false)
const intentado = ref(false)
const error = ref('')

const hoyFecha = computed(() => deDia(props.opciones.hoy))
const fecha = computed<Date | null>({
  get: () => (b.fecha ? deDia(b.fecha) : null),
  set: (v) => (b.fecha = v ? diaISO(v) : null),
})
const agencia = computed(() => props.opciones.agencias.find((a) => a.id === b.agencia) ?? null)
const moneda = computed(() => agencia.value?.moneda ?? 'GTQ')
const bono = computed(() => bonoPrevisto(Math.round((b.importe ?? 0) * 100), agencia.value?.porcentajeBonificacion ?? null, b.aplicaBono))
const errores = computed(() => erroresDeposito(b, props.opciones.hoy))
/** Los errores se muestran después del primer intento. */
const mostrar = computed(() => (intentado.value ? errores.value : {}))

async function enviar() {
  intentado.value = true
  error.value = ''
  if (Object.keys(errores.value).length) return
  enviando.value = true
  try {
    const d = await registrarDeposito(aNuevoDeposito(b))
    emit('registrado', d)
    Object.assign(b, nuevo())
    intentado.value = false
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo registrar el depósito.'
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.campos {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem 1rem;
}
@container (min-width: 40rem) {
  .campos {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .campo-ancho {
    grid-column: 1 / -1;
  }
}
@container (min-width: 64rem) {
  .campos {
    grid-template-columns: 2fr 1fr 1fr 1fr;
  }
  .campo-ancho {
    grid-column: auto;
  }
}
.campo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.campo > label:first-child {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.error {
  color: var(--p-red-500);
}
</style>
