<!--
  Bitácora de una salida o de un boleto: quién hizo qué y cuándo, de lo más
  viejo a lo más nuevo. Se alimenta de `GET /api/bitacora/{tipo}/{id}`.
-->
<template>
  <Dialog :visible="visible" modal :header="`Bitácora · ${tipo === 'salida' ? 'Salida' : 'Boleto'} ${id}`" class="w-[min(34rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <div class="flex flex-col gap-3">
      <Skeleton v-if="cargando" height="9rem" />
      <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
      <template v-else-if="bitacora">
        <p class="m-0 text-sm text-muted-color">{{ bitacora.etiqueta }}</p>
        <p v-if="!bitacora.entradas.length" class="m-0 py-6 text-center text-sm text-muted-color">Sin movimientos registrados. Los registros migrados del sistema anterior no traen bitácora.</p>
        <ol v-else class="m-0 flex list-none flex-col p-0">
          <li v-for="e in bitacora.entradas" :key="e.id" class="entrada">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
              <span class="font-semibold">{{ e.etiqueta }}</span>
              <time class="text-xs tabular-nums text-muted-color" :datetime="e.fecha">{{ fechaHora(e.fecha) }}</time>
            </div>
            <div class="text-sm text-muted-color">{{ quien(e) }}</div>
            <ul v-if="lineas(e).length" class="m-0 mt-1 list-none p-0 text-sm">
              <li v-for="linea in lineas(e)" :key="linea">{{ linea }}</li>
            </ul>
          </li>
        </ol>
      </template>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { fetchBitacora } from '@/core/bitacora/api'
import { lineasDeDetalle, quien } from '@/core/bitacora/formato'
import type { Bitacora, EntradaBitacora, TipoBitacora } from '@/core/bitacora/types'
import { HttpError } from '@/core/http'

const props = defineProps<{ visible: boolean; tipo: TipoBitacora; id: number }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

const bitacora = ref<Bitacora | null>(null)
const cargando = ref(false)
const error = ref('')
let pedido = 0

async function cargar() {
  const mio = ++pedido
  bitacora.value = null
  error.value = ''
  cargando.value = true
  try {
    const b = await fetchBitacora(props.tipo, props.id)
    if (mio === pedido) bitacora.value = b
  } catch (e) {
    if (mio === pedido) error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver esta bitácora.' : 'No se pudo cargar la bitácora.'
  } finally {
    if (mio === pedido) cargando.value = false
  }
}

const lineas = (e: EntradaBitacora) => lineasDeDetalle(props.tipo, e)
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>

<style scoped>
.entrada {
  position: relative;
  padding: 0 0 1rem 1rem;
  border-left: 2px solid var(--p-content-border-color);
}
.entrada::before {
  content: '';
  position: absolute;
  left: -0.3rem;
  top: 0.3rem;
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: var(--p-primary-color);
}
.entrada:last-child {
  padding-bottom: 0;
}
</style>
