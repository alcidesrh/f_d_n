<!--
  Registrar depósito de agencia (menú "Agencia" del legado, ADR-034). Tras
  registrar se queda en el formulario vacío para el siguiente y ofrece ir al
  depósito en el listado, donde se acredita.
-->
<template>
  <div class="mx-auto flex w-full max-w-5xl flex-col gap-3">
    <section class="panel flex flex-col gap-4">
      <Message severity="info" :closable="false" class="!text-sm">
        El depósito queda <strong>registrado</strong>; el saldo de la agencia cambia cuando se acredita desde «Buscar depósito».
      </Message>
      <RegistrarDepositoForm v-if="opciones" :opciones="opciones" @registrado="registrado" />
      <Skeleton v-else-if="!error" height="14rem" />
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </section>

    <section v-if="recientes.length" class="panel flex flex-col gap-2">
      <h2 class="text-sm font-semibold">Registrados en esta visita</h2>
      <ul class="flex flex-col divide-y divide-surface-200">
        <li v-for="d in recientes" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
          <span class="min-w-0 truncate">
            <span class="font-medium">#{{ d.id }}</span> · {{ d.agencia.nombre }} · boleta {{ d.numeroBoleta }}
          </span>
          <span class="flex items-center gap-2">
            <span class="tabular-nums">{{ d.importe.texto }}</span>
            <Button text size="small" label="Ver" @click="router.push({ name: 'agencia-depositos', query: { id: String(d.id) } })" />
          </span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, shallowRef } from 'vue'
import { useRouter } from 'vue-router'
import { fetchOpcionesDepositos } from '@/core/agencia/api'
import type { DepositoFila, OpcionesDepositos } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import RegistrarDepositoForm from './RegistrarDepositoForm.vue'

const router = useRouter()
const opciones = shallowRef<OpcionesDepositos | null>(null)
const error = ref('')
const recientes = ref<DepositoFila[]>([])

function registrado(d: DepositoFila) {
  notify.success(`Depósito ${d.numeroBoleta} registrado`)
  recientes.value = [d, ...recientes.value].slice(0, 10)
}

onMounted(async () => {
  try {
    opciones.value = await fetchOpcionesDepositos()
  } catch (e) {
    error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para registrar depósitos (agencia.depositar).' : 'No se pudieron cargar las agencias.'
  }
})
</script>

<style scoped>
.panel {
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
}
</style>
