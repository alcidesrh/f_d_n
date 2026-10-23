<!--
  Editar una compra web: corrige los datos del comprador (nombre, correo,
  teléfono, NIT y a nombre de quién sale la factura). La factura ya
  emitida no cambia: «Crear factura» la reemite con estos datos.
-->
<template>
  <Dialog :visible="visible" modal :header="compra ? `Editar compra #${compra.id}` : ''" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <form v-if="compra" class="flex flex-col gap-3" @submit.prevent="guardar">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Nombre</span>
          <InputText v-model="datos.nombre" required maxlength="255" autocomplete="given-name" :disabled="guardando" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Apellido</span>
          <InputText v-model="apellido" maxlength="50" autocomplete="family-name" :disabled="guardando" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Correo</span>
          <InputText v-model="datos.email" type="email" inputmode="email" required maxlength="50" autocomplete="email" :disabled="guardando" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Teléfono</span>
          <InputText v-model="telefono" type="tel" inputmode="tel" maxlength="15" autocomplete="tel" :disabled="guardando" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">NIT</span>
          <InputText v-model="datos.nit" maxlength="20" placeholder="CF" :disabled="guardando" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Factura a nombre de</span>
          <InputText v-model="nombreFactura" maxlength="255" :placeholder="datos.nombre" :disabled="guardando" fluid />
          <small class="text-muted-color">Vacío: el del comprador. Con otro NIT se usa el de la SAT.</small>
        </label>
      </div>
      <Message severity="info" :closable="false">La factura ya emitida no cambia. Para facturar con estos datos use «Crear factura».</Message>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <button type="submit" hidden />
    </form>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Guardar" :loading="guardando" :disabled="!datos.nombre.trim() || !datos.email.trim()" @click="guardar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { editarComprador } from '@/core/pagina-web/api'
import type { CompraWeb, DatosComprador } from '@/core/pagina-web/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; compra: CompraWeb | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [compra: CompraWeb | null] }>()

const datos = reactive<DatosComprador>({ nombre: '', apellido: null, email: '', telefono: null, nit: 'CF', nombreFactura: null })
const apellido = computed({ get: () => datos.apellido ?? '', set: (v: string) => (datos.apellido = v.trim() ? v : null) })
const telefono = computed({ get: () => datos.telefono ?? '', set: (v: string) => (datos.telefono = v.trim() ? v : null) })
const nombreFactura = computed({ get: () => datos.nombreFactura ?? '', set: (v: string) => (datos.nombreFactura = v.trim() ? v : null) })
const guardando = ref(false)
const error = ref('')

function abrir() {
  const c = props.compra?.comprador
  Object.assign(datos, { nombre: c?.nombres ?? '', apellido: c?.apellido ?? null, email: c?.email ?? '', telefono: c?.telefono ?? null, nit: c?.nit ?? 'CF', nombreFactura: c?.nombreFactura ?? null })
  error.value = ''
}

async function guardar() {
  if (!props.compra || !datos.nombre.trim() || !datos.email.trim()) return
  guardando.value = true
  error.value = ''
  try {
    const r = await editarComprador(props.compra.id, { ...datos, nit: datos.nit.trim() || 'CF' })
    notify.success('Datos del comprador guardados')
    emit('listo', r.compra)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudieron guardar los datos.'
  } finally {
    guardando.value = false
  }
}
</script>
