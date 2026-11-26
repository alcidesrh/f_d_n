<!-- "Perfil" de Mi cuenta: la foto (para el chat) y los datos personales (`MiCuentaController`). -->
<template>
  <div v-if="cuenta.cuenta" class="flex flex-col gap-6">
    <div class="flex flex-col items-start gap-3 @xl:flex-row @xl:items-center">
      <ChatAvatar :id="cuenta.cuenta.id" :nombre="cuenta.nombre" :foto="cuenta.foto" tamano="xl" />
      <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
          <Button label="Cambiar foto" severity="secondary" size="small" :loading="subiendo" @click="elegir?.click()">
            <template #icon><icon name="photo-camera" size="1rem" /></template>
          </Button>
          <Button v-if="cuenta.cuenta.foto" label="Quitar" severity="danger" text size="small" :disabled="subiendo" @click="quitar">
            <template #icon><icon name="delete-outline" size="1rem" /></template>
          </Button>
        </div>
        <p class="m-0 text-xs text-muted-color">JPG, PNG o WebP. Se recorta en cuadrado.</p>
      </div>
      <input ref="elegir" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFoto" />
    </div>

    <section>
      <h3 class="mb-3 text-sm font-semibold">Datos personales</h3>
      <FormKit :key="`datos-${cuenta.cuenta.id}-${version}`" type="form" :actions="false" :value="inicial" @submit="guardarDatos">
        <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-2">
          <FormKit type="InputText" name="nombre" label="Nombre" validation="required|length:0,255" fluid />
          <FormKit type="InputText" name="apellido" label="Apellido" validation="length:0,50" fluid />
          <FormKit type="InputText" name="email" label="Correo" validation="email|length:0,50" autocomplete="email" fluid />
          <FormKit type="InputText" name="telefono" label="Teléfono" validation="length:0,15" autocomplete="tel" fluid />
          <FormKit type="InputText" name="nit" label="NIT" validation="length:0,20" fluid />
          <FormKit type="InputText" name="direccion" label="Dirección" validation="length:0,255" autocomplete="street-address" fluid />
        </div>
        <div class="flex justify-end">
          <Button type="submit" label="Guardar cambios" :loading="guardando" />
        </div>
      </FormKit>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useCuentaStore } from '@/core/cuenta/store'
import { notify } from '@/core/notify'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import { recortarCuadrada } from './foto'
import { mensajeDeError } from './secciones'

const cuenta = useCuentaStore()
const elegir = ref<HTMLInputElement | null>(null)
const subiendo = ref(false)
const guardando = ref(false)
/** Cambia al guardar para que el formulario vuelva a leer los valores guardados. */
const version = ref(0)

const inicial = computed(() => {
  const c = cuenta.cuenta
  return {
    nombre: c?.nombre ?? '',
    apellido: c?.apellido ?? '',
    email: c?.email ?? '',
    telefono: c?.telefono ?? '',
    nit: c?.nit ?? '',
    direccion: c?.direccion ?? '',
  }
})

async function onFoto(evento: Event) {
  const entrada = evento.target as HTMLInputElement
  const archivo = entrada.files?.[0]
  entrada.value = ''
  if (!archivo) return
  subiendo.value = true
  try {
    await cuenta.subirFoto(await recortarCuadrada(archivo), 'foto.jpg')
    notify.success('Foto actualizada.')
  } catch (e) {
    notify.error(mensajeDeError(e, 'No se pudo cambiar la foto.'))
  } finally {
    subiendo.value = false
  }
}

async function quitar() {
  subiendo.value = true
  try {
    await cuenta.quitarFoto()
    notify.success('Foto eliminada.')
  } catch (e) {
    notify.error(mensajeDeError(e, 'No se pudo quitar la foto.'))
  } finally {
    subiendo.value = false
  }
}

async function guardarDatos(v: Record<'nombre' | 'apellido' | 'email' | 'telefono' | 'nit' | 'direccion', string>) {
  guardando.value = true
  try {
    await cuenta.guardar({
      nombre: v.nombre,
      apellido: v.apellido || null,
      email: v.email || null,
      telefono: v.telefono || null,
      nit: v.nit || null,
      direccion: v.direccion || null,
    })
    version.value++
    notify.success('Datos guardados.')
  } catch (e) {
    notify.error(mensajeDeError(e, 'No se pudieron guardar los datos.'))
  } finally {
    guardando.value = false
  }
}
</script>
