<!--
  "Mi cuenta" (`/mi-cuenta`): lo que el usuario puede editar de su propio
  perfil: foto, datos personales y contraseña (`MiCuentaController`). El
  usuario, los roles y los permisos los administra otra persona.
-->
<template>
  <div>
    <PageHead />
    <div v-if="cuenta.cuenta" class="form-fluid @container">
      <div class="grid grid-cols-1 items-start gap-4 @4xl:grid-cols-[18rem_minmax(0,1fr)]">
        <section class="card flex flex-col items-center gap-4 text-center">
          <ChatAvatar
            :id="cuenta.cuenta.id"
            :nombre="cuenta.nombre"
            :foto="cuenta.foto"
            tamano="xl"
          />
          <div class="min-w-0 max-w-full">
            <div class="truncate text-lg font-semibold">{{ cuenta.nombre }}</div>
            <div class="truncate text-sm text-muted-color">@{{ cuenta.cuenta.username }}</div>
          </div>
          <div class="flex flex-wrap justify-center gap-2">
            <Button
              label="Cambiar foto"
              severity="secondary"
              size="small"
              :loading="subiendo"
              @click="elegir?.click()"
            >
              <template #icon><icon name="photo-camera" size="1rem" /></template>
            </Button>
            <Button
              v-if="cuenta.cuenta.foto"
              label="Quitar"
              severity="danger"
              text
              size="small"
              :disabled="subiendo"
              @click="quitar"
            >
              <template #icon><icon name="delete-outline" size="1rem" /></template>
            </Button>
          </div>
          <input
            ref="elegir"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
            @change="onFoto"
          />
          <p class="text-xs text-muted-color">JPG, PNG o WebP. Se recorta en cuadrado.</p>

          <dl
            v-if="vinculos.length"
            class="w-full border-t border-surface-200 pt-3 text-left text-sm"
          >
            <div v-for="v in vinculos" :key="v.etiqueta" class="flex justify-between gap-3 py-0.5">
              <dt class="text-muted-color">{{ v.etiqueta }}</dt>
              <dd class="truncate font-medium">{{ v.valor }}</dd>
            </div>
          </dl>
        </section>

        <div class="flex min-w-0 flex-col gap-4">
          <section class="card">
            <h2 class="mb-3 text-base font-semibold">Datos personales</h2>
            <FormKit
              :key="`datos-${cuenta.cuenta.id}-${version}`"
              type="form"
              :actions="false"
              :value="inicial"
              @submit="guardarDatos"
            >
              <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-2">
                <FormKit
                  type="InputText"
                  name="nombre"
                  label="Nombre"
                  validation="required|length:0,255"
                  fluid
                />
                <FormKit
                  type="InputText"
                  name="apellido"
                  label="Apellido"
                  validation="length:0,50"
                  fluid
                />
                <FormKit
                  type="InputText"
                  name="email"
                  label="Correo"
                  validation="email|length:0,50"
                  autocomplete="email"
                  fluid
                />
                <FormKit
                  type="InputText"
                  name="telefono"
                  label="Teléfono"
                  validation="length:0,15"
                  autocomplete="tel"
                  fluid
                />
                <FormKit type="InputText" name="nit" label="NIT" validation="length:0,20" fluid />
                <FormKit
                  type="InputText"
                  name="direccion"
                  label="Dirección"
                  validation="length:0,255"
                  autocomplete="street-address"
                  fluid
                />
              </div>
              <div class="flex justify-end">
                <Button type="submit" label="Guardar cambios" :loading="guardando" />
              </div>
            </FormKit>
          </section>

          <section class="card">
            <h2 class="mb-3 text-base font-semibold">Cambiar contraseña</h2>
            <FormKit
              :key="`clave-${claveForm}`"
              type="form"
              :actions="false"
              @submit="cambiarClave"
            >
              <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-3">
                <FormKit
                  type="Password"
                  name="actual"
                  label="Contraseña actual"
                  validation="required"
                  autocomplete="current-password"
                  fluid
                />
                <FormKit
                  type="Password"
                  name="password"
                  label="Nueva contraseña"
                  :validation="`required|length:${MINIMO}`"
                  :validation-messages="{ length: `Al menos ${MINIMO} caracteres.` }"
                  autocomplete="new-password"
                  fluid
                />
                <FormKit
                  type="Password"
                  name="password_confirm"
                  label="Repetir contraseña"
                  validation="required|confirm"
                  :validation-messages="{ confirm: 'Las contraseñas no coinciden.' }"
                  autocomplete="new-password"
                  fluid
                />
              </div>
              <div class="flex justify-end">
                <Button type="submit" label="Cambiar contraseña" :loading="cambiando" />
              </div>
            </FormKit>
          </section>
        </div>
      </div>
    </div>
    <div v-else class="card flex justify-center py-12">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { cambiarPassword } from '@/core/cuenta/api'
import { useCuentaStore } from '@/core/cuenta/store'
import { notify } from '@/core/notify'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import PageHead from '@/shared/ui/PageHead.vue'
import { recortarCuadrada } from './foto'

/** Igual que `MiCuentaController::MINIMO_PASSWORD`. */
const MINIMO = 6

const cuenta = useCuentaStore()
const elegir = ref<HTMLInputElement | null>(null)
const subiendo = ref(false)
const guardando = ref(false)
const cambiando = ref(false)
const claveForm = ref(0)
/** Cambia al guardar para que el formulario vuelva a leer los valores guardados. */
const version = ref(0)

onMounted(() => void cuenta.cargar())

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

/** Dónde trabaja: empresa, estación o agencia (solo lectura). */
const vinculos = computed(() =>
  [
    { etiqueta: 'Empresa', valor: cuenta.cuenta?.empresa },
    { etiqueta: 'Estación', valor: cuenta.cuenta?.estacion },
    { etiqueta: 'Agencia', valor: cuenta.cuenta?.agencia },
  ].filter((v): v is { etiqueta: string; valor: string } => Boolean(v.valor)),
)

const mensaje = (e: unknown, defecto: string) =>
  e instanceof Error && e.message ? e.message : defecto

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
    notify.error(mensaje(e, 'No se pudo cambiar la foto.'))
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
    notify.error(mensaje(e, 'No se pudo quitar la foto.'))
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
    notify.error(mensaje(e, 'No se pudieron guardar los datos.'))
  } finally {
    guardando.value = false
  }
}

async function cambiarClave(v: { actual: string; password: string }) {
  cambiando.value = true
  try {
    await cambiarPassword({ actual: v.actual, password: v.password })
    claveForm.value++
    notify.success('Contraseña cambiada.')
  } catch (e) {
    notify.error(mensaje(e, 'No se pudo cambiar la contraseña.'))
  } finally {
    cambiando.value = false
  }
}
</script>
