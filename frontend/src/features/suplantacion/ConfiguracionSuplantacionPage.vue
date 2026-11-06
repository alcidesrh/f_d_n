<!--
  Configuración de la suplantación de usuarios (`/configuracion/suplantacion`):
  si un administrador del sistema puede entrar como otro usuario, cuánto dura,
  qué puede hacer en el chat y el correo, si se le avisa al usuario y si hay
  que escribir un motivo. Capacidad `iam.suplantar` (solo administradores del
  sistema).
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar>
      <template #start>
        <PageHead title="" />
      </template>
    </Toolbar>

    <Skeleton v-if="!config && !error" height="20rem" class="mx-2" />
    <Message v-else-if="!config" severity="error" :closable="false" class="mx-2">{{
      error
    }}</Message>
    <div v-else class="grid grid-cols-1 gap-4 px-2 @5xl:grid-cols-2">
      <section class="flex flex-col gap-5" aria-label="Suplantación">
        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="suplantacion-activa" class="font-medium"
              >Permitir entrar como otro usuario</label
            >
            <p class="m-0 text-xs text-muted-color">
              Solo un administrador del sistema, nunca como otro administrador del sistema. Se hace
              desde el listado de usuarios: «Entrar como este usuario».
            </p>
          </div>
          <ToggleSwitch v-model="form.activa" input-id="suplantacion-activa" />
        </div>

        <label class="flex flex-col gap-1.5">
          <span class="font-medium">Duración (minutos)</span>
          <InputNumber
            v-model="form.duracionMinutos"
            :min="config.catalogo.duracion.min"
            :max="config.catalogo.duracion.max"
            show-buttons
            fluid
            :disabled="!form.activa"
          />
          <span class="text-xs text-muted-color"
            >No se renueva: al vencer vuelve sola a la cuenta del administrador. De
            {{ config.catalogo.duracion.min }} a {{ config.catalogo.duracion.max }} minutos.</span
          >
        </label>

        <div class="flex flex-col gap-1.5">
          <span class="font-medium">Chat y correo del usuario</span>
          <SelectButton
            v-model="form.chat"
            :options="config.catalogo.chat"
            option-label="etiqueta"
            option-value="valor"
            :allow-empty="false"
            :disabled="!form.activa"
          />
          <span class="text-xs text-muted-color">{{ DESCRIPCION_CHAT[form.chat] }}</span>
        </div>
      </section>

      <section class="flex flex-col gap-5" aria-label="Transparencia">
        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="suplantacion-avisar" class="font-medium">Avisar al usuario</label>
            <p class="m-0 text-xs text-muted-color">
              Recibe en su «Bitácora del sistema» del chat quién entró a su cuenta, cuándo y por
              qué.
            </p>
          </div>
          <ToggleSwitch v-model="form.avisarUsuario" input-id="suplantacion-avisar" />
        </div>
        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="suplantacion-motivo" class="font-medium">Exigir un motivo</label>
            <p class="m-0 text-xs text-muted-color">
              Hay que escribir por qué se entra (queda en la bitácora diaria y en la de accesos del
              usuario).
            </p>
          </div>
          <ToggleSwitch v-model="form.exigirMotivo" input-id="suplantacion-motivo" />
        </div>
        <p class="m-0 text-xs text-muted-color">
          Siempre, sin importar esta configuración: cada petición hecha como el usuario queda en la
          bitácora diaria con el nombre de quien la hizo, y mientras dura no se puede cambiar la
          contraseña, los datos de la cuenta ni los accesos.
        </p>
      </section>

      <section class="flex flex-col gap-3 self-start @5xl:col-span-2">
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span v-if="config.actualizadaEn" class="text-xs text-muted-color"
            >Último cambio: {{ fechaHora(config.actualizadaEn)
            }}<template v-if="config.actualizadaPor">
              por {{ config.actualizadaPor }}</template
            ></span
          >
          <span v-else />
          <div class="flex gap-2">
            <Button
              label="Descartar"
              text
              :disabled="!cambio || guardando"
              @click="aplicar(config)"
            />
            <Button label="Guardar" :loading="guardando" :disabled="!cambio" @click="guardar"
              ><template #icon><icon name="save-outline" class="mr-1" /></template
            ></Button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { notify } from '@/core/notify'
import {
  fetchConfiguracionSuplantacion,
  guardarConfiguracionSuplantacion,
  mensajeDeError,
} from '@/core/suplantacion/api'
import type {
  CambiosSuplantacion,
  ConfiguracionSuplantacion,
  ModoChat,
} from '@/core/suplantacion/tipos'

type Formulario = Required<CambiosSuplantacion>

const DESCRIPCION_CHAT: Record<ModoChat, string> = {
  permitir: 'Puede leer y escribir como el usuario (queda en la bitácora).',
  lectura:
    'Puede leer sus conversaciones y correos, sin escribir, sin marcarlos como leídos y sin aparecer en línea.',
  bloquear: 'No puede abrir el chat ni el correo del usuario.',
}

const config = ref<ConfiguracionSuplantacion | null>(null)
const form = reactive<Formulario>({
  activa: true,
  duracionMinutos: 60,
  chat: 'lectura',
  avisarUsuario: true,
  exigirMotivo: true,
})
const guardando = ref(false)
const error = ref('')

const fechaHora = (iso: string) =>
  new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(iso),
  )

function desdeConfig(c: ConfiguracionSuplantacion): Formulario {
  return {
    activa: c.activa,
    duracionMinutos: c.duracionMinutos,
    chat: c.chat,
    avisarUsuario: c.avisarUsuario,
    exigirMotivo: c.exigirMotivo,
  }
}
const cambio = computed(
  () => !!config.value && JSON.stringify(form) !== JSON.stringify(desdeConfig(config.value)),
)

function aplicar(c: ConfiguracionSuplantacion) {
  config.value = c
  Object.assign(form, desdeConfig(c))
  error.value = ''
}

onMounted(async () => {
  try {
    aplicar(await fetchConfiguracionSuplantacion())
  } catch (e) {
    error.value = mensajeDeError(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(await guardarConfiguracionSuplantacion({ ...form }))
    notify.success('Configuración de la suplantación guardada')
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}
</script>
