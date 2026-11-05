<!--
  Correo (MailPace) en la administración (`/configuracion/correo`, ADR-048):
  enviar por MailPace o por `MAILER_DSN`, el token de cada dominio verificado,
  el dominio de las direcciones y el de entrada, la clave del webhook y las
  direcciones del sistema (remitentes y buzón de contacto). Los tokens nunca
  llegan al navegador: vacío, se conserva el guardado. Capacidad
  `config.proveedores`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar>
      <template #start>
        <PageHead title="" />
      </template>
    </Toolbar>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-4 px-2 @4xl:grid-cols-[minmax(0,40rem)_minmax(0,1fr)]">
      <section class="flex flex-col gap-5">
        <Skeleton v-if="!config" height="24rem" />
        <template v-else>
          <div class="flex items-center justify-between gap-3">
            <div>
              <label for="correo-activo" class="font-medium">Enviar por MailPace</label>
              <p class="m-0 text-xs text-muted-color">Todo el correo del sistema: el de los usuarios, los boletos, los reportes y el contacto de la página. Apagado, sale por <code>MAILER_DSN</code> (en desarrollo, a ninguna parte).</p>
            </div>
            <ToggleSwitch v-model="form.activo" input-id="correo-activo" class="shrink-0" />
          </div>
          <Message v-if="form.activo && sinToken.length" severity="warn" :closable="false">
            Sin token, no sale el correo de: {{ sinToken.join(', ') }}.
          </Message>

          <fieldset class="flex flex-col gap-3 rounded-border border border-surface p-3">
            <legend class="px-1 font-medium">Dominios de envío</legend>
            <p class="m-0 text-xs text-muted-color">MailPace da un token por dominio verificado (Domain → API Tokens). Cada correo sale con el del dominio de su remitente.</p>
            <div v-for="(d, i) in form.dominios" :key="i" class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto]">
              <InputText v-model.trim="d.dominio" placeholder="ejemplo.com" :invalid="!!d.dominio && !dominioValido(d.dominio)" :disabled="!!d.guardado" aria-label="Dominio" fluid />
              <div class="col-span-2 flex flex-col gap-1 sm:col-span-1">
                <Password
                  v-model="d.token"
                  :feedback="false"
                  toggle-mask
                  :placeholder="d.guardado === 'guardado' ? 'Token guardado: sin cambios' : 'Token del dominio'"
                  :invalid="d.guardado === 'ilegible' && !d.token"
                  :input-props="{ autocomplete: 'new-password', 'aria-label': `Token de ${d.dominio || 'el dominio'}` }"
                  fluid
                />
                <span v-if="d.guardado === 'ilegible' && !d.token" class="text-xs text-red-600">Se cifró con otro <code>APP_SECRET</code>: vuelva a cargarlo.</span>
              </div>
              <Button severity="secondary" text rounded aria-label="Quitar" class="col-start-2 row-start-1 sm:col-start-auto sm:row-start-auto" @click="form.dominios.splice(i, 1)"><template #icon><icon name="delete-outline" /></template></Button>
            </div>
            <div>
              <Button label="Agregar dominio" size="small" text @click="form.dominios.push({ dominio: '', token: '', guardado: null })"><template #icon><icon name="add" class="mr-1" /></template></Button>
            </div>
            <div class="grid grid-cols-[minmax(0,1fr)_6rem] gap-2">
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Servidor SMTP</span>
                <InputText v-model.trim="form.servidor" :invalid="!dominioValido(form.servidor)" fluid />
              </label>
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Puerto</span>
                <InputNumber v-model="form.puerto" :min="1" :max="65535" :use-grouping="false" fluid />
              </label>
            </div>
          </fieldset>

          <fieldset class="flex flex-col gap-3 rounded-border border border-surface p-3">
            <legend class="px-1 font-medium">Correo de los usuarios</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Dominio de las direcciones</span>
                <InputText v-model.trim="form.dominio" :invalid="!dominioValido(form.dominio)" fluid />
                <span class="text-xs text-muted-color">usuario@{{ form.dominio || '…' }}</span>
              </label>
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Dominio de entrada</span>
                <InputText v-model.trim="form.dominioEntrada" :placeholder="`Vacío: ${form.dominio || 'el mismo'}`" :invalid="!!form.dominioEntrada && !dominioValido(form.dominioEntrada)" fluid />
                <span class="text-xs text-muted-color">MailPace solo recibe en un subdominio: las respuestas vuelven a él.</span>
              </label>
            </div>
            <label class="flex flex-col gap-1.5">
              <span class="text-sm">Clave pública del webhook</span>
              <InputText v-model.trim="form.claveWebhook" placeholder="Vacía: no se acepta correo entrante" :invalid="!claveValida(form.claveWebhook)" class="font-mono text-xs" fluid />
              <span class="text-xs text-muted-color">En MailPace: Domain → Inbound → Webhook → Public Key Verification.</span>
            </label>
          </fieldset>

          <fieldset class="flex flex-col gap-3 rounded-border border border-surface p-3">
            <legend class="px-1 font-medium">Direcciones del sistema</legend>
            <div class="flex flex-col gap-3">
              <label v-for="c in camposDirecciones" :key="c.campo" class="grid grid-cols-1 items-center gap-x-3 gap-y-1 sm:grid-cols-[12rem_minmax(0,1fr)]">
                <span class="text-sm">{{ c.nombre }}</span>
                <InputText v-model.trim="form[c.campo]" :invalid="!direccionValida(form[c.campo])" fluid />
                <span class="text-xs text-muted-color sm:col-start-2">{{ c.ayuda }}</span>
              </label>
            </div>
          </fieldset>

          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span v-if="config.actualizadaEn" class="text-xs text-muted-color">Último cambio: {{ fechaHora(config.actualizadaEn) }}<template v-if="config.actualizadaPor"> por {{ config.actualizadaPor }}</template></span>
            <span v-else />
            <Button label="Guardar" :loading="guardando" :disabled="!cambio || !valido" @click="guardar"><template #icon><icon name="save-outline" class="mr-1" /></template></Button>
          </div>
        </template>
      </section>

      <aside class="flex flex-col gap-4">
        <section v-if="config" class="flex flex-col gap-2 text-sm">
          <h3 class="m-0 text-sm font-semibold">Webhook de entrada</h3>
          <p class="m-0 text-muted-color">Pegue esta URL en MailPace (Domain → Inbound) para que lo que llega al dominio de entrada aparezca en el correo de cada usuario.</p>
          <div class="flex items-center gap-1.5">
            <code class="min-w-0 flex-1 truncate rounded-border bg-surface-100 px-2 py-1.5 text-xs dark:bg-surface-800" :title="config.webhook">{{ config.webhook }}</code>
            <Button severity="secondary" text rounded aria-label="Copiar la URL" @click="copiar"><template #icon><icon :name="copiado ? 'check' : 'content-copy-outline'" /></template></Button>
          </div>
          <Tag :value="config.claveWebhook ? 'Firma verificada' : 'Sin clave: se rechaza'" :severity="config.claveWebhook ? 'success' : 'warn'" class="self-start" />
        </section>

        <section class="flex flex-col gap-3 text-sm">
          <h3 class="m-0 text-sm font-semibold">Enviar una prueba</h3>
          <p class="m-0 text-muted-color">Manda un correo desde <code>no-responder@</code> el dominio elegido con el token <b>guardado</b>. Funciona aunque MailPace esté apagado.</p>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
            <Select v-model="prueba.dominio" :options="conToken" placeholder="Dominio" fluid />
            <InputText v-model.trim="prueba.para" type="email" placeholder="Para (su correo)" fluid />
          </div>
          <div>
            <Button label="Enviar prueba" size="small" severity="secondary" :loading="probando" :disabled="!prueba.dominio || !direccionValida(prueba.para)" @click="probar">
              <template #icon><icon name="send-outline" class="mr-1" /></template>
            </Button>
          </div>
          <Message v-if="pruebaOk" severity="success" :closable="false">MailPace la aceptó. Revise la bandeja de {{ pruebaOk }}.</Message>
          <Message v-if="errorPrueba" severity="error" :closable="false">{{ errorPrueba }}</Message>
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { claveValida, direccionValida, dominioValido, fetchConfigCorreo, guardarConfigCorreo, mensajeDeError, probarConfigCorreo } from '@/core/proveedores/api'
import type { CambiosCorreo, ConfiguracionCorreo, DominioEnvio } from '@/core/proveedores/types'
import { notify } from '@/core/notify'

type Direccion = 'remitenteBoletos' | 'remitenteReportes' | 'buzonContacto'
interface FilaDominio {
  dominio: string
  token: string
  /** Lo que hay guardado para ese dominio (null: nada, o una fila nueva). */
  guardado: DominioEnvio['token']
}

const camposDirecciones: Array<{ campo: Direccion; nombre: string; ayuda: string }> = [
  { campo: 'remitenteBoletos', nombre: 'Remitente de los boletos', ayuda: 'También el del contacto de la página.' },
  { campo: 'remitenteReportes', nombre: 'Remitente de los reportes', ayuda: 'Reportes y avisos programados.' },
  { campo: 'buzonContacto', nombre: 'Buzón de contacto', ayuda: 'Recibe el formulario de la página.' },
]

const config = ref<ConfiguracionCorreo | null>(null)
const form = reactive({
  activo: false,
  servidor: '',
  puerto: 465 as number | null,
  dominios: [] as FilaDominio[],
  dominio: '',
  dominioEntrada: '',
  claveWebhook: '',
  remitenteBoletos: '',
  remitenteReportes: '',
  buzonContacto: '',
})
const guardando = ref(false)
const error = ref('')
const prueba = reactive({ dominio: '', para: '' })
const probando = ref(false)
const pruebaOk = ref('')
const errorPrueba = ref('')
const copiado = ref(false)

const dominioDe = (direccion: string) => direccion.slice(direccion.lastIndexOf('@') + 1).toLowerCase()
const tieneToken = (f: FilaDominio) => f.token !== '' || f.guardado === 'guardado'

/** Remitentes cuyo dominio no tendrá token al guardar. */
const sinToken = computed(() => {
  const con = new Set(form.dominios.filter(tieneToken).map((f) => f.dominio.toLowerCase()))
  const remitentes = [`usuario@${form.dominio}`, form.remitenteBoletos, form.remitenteReportes]
  return [...new Set(remitentes.map(dominioDe).filter((d) => d && !con.has(d)))]
})
const conToken = computed(() => (config.value?.dominiosEnvio ?? []).filter((d) => d.token === 'guardado').map((d) => d.dominio))

const valido = computed(() => {
  const nombres = form.dominios.map((f) => f.dominio.toLowerCase())
  return (
    form.dominios.every((f) => dominioValido(f.dominio)) &&
    new Set(nombres).size === nombres.length &&
    dominioValido(form.servidor) &&
    !!form.puerto &&
    dominioValido(form.dominio) &&
    (!form.dominioEntrada || dominioValido(form.dominioEntrada)) &&
    claveValida(form.claveWebhook) &&
    camposDirecciones.every((c) => direccionValida(form[c.campo])) &&
    (!form.activo || form.dominios.some(tieneToken))
  )
})

const cambios = (): CambiosCorreo => ({
  activo: form.activo,
  servidor: form.servidor,
  puerto: form.puerto ?? 465,
  dominiosEnvio: form.dominios.map((f) => ({ dominio: f.dominio.toLowerCase(), token: f.token })),
  dominio: form.dominio.toLowerCase(),
  dominioEntrada: form.dominioEntrada.toLowerCase(),
  claveWebhook: form.claveWebhook,
  remitenteBoletos: form.remitenteBoletos,
  remitenteReportes: form.remitenteReportes,
  buzonContacto: form.buzonContacto,
})
const original = (c: ConfiguracionCorreo): CambiosCorreo => ({
  activo: c.activo,
  servidor: c.servidor,
  puerto: c.puerto,
  dominiosEnvio: c.dominiosEnvio.map((d) => ({ dominio: d.dominio, token: '' })),
  dominio: c.dominio,
  dominioEntrada: c.dominioEntrada ?? '',
  claveWebhook: c.claveWebhook ?? '',
  remitenteBoletos: c.remitenteBoletos,
  remitenteReportes: c.remitenteReportes,
  buzonContacto: c.buzonContacto,
})
const cambio = computed(() => !!config.value && JSON.stringify(cambios()) !== JSON.stringify(original(config.value)))
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

function aplicar(c: ConfiguracionCorreo) {
  config.value = c
  const o = original(c)
  Object.assign(form, { ...o, dominios: c.dominiosEnvio.map((d) => ({ dominio: d.dominio, token: '', guardado: d.token })) })
  if (!conToken.value.includes(prueba.dominio)) prueba.dominio = conToken.value[0] ?? ''
}

onMounted(async () => {
  try {
    aplicar(await fetchConfigCorreo())
  } catch (e) {
    error.value = mensajeDeError(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(await guardarConfigCorreo(cambios()))
    notify.success('Configuración del correo guardada')
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

async function probar() {
  probando.value = true
  pruebaOk.value = ''
  errorPrueba.value = ''
  try {
    await probarConfigCorreo(prueba.dominio, prueba.para)
    pruebaOk.value = prueba.para
  } catch (e) {
    errorPrueba.value = mensajeDeError(e)
  } finally {
    probando.value = false
  }
}

async function copiar() {
  if (!config.value) return
  try {
    await navigator.clipboard.writeText(config.value.webhook)
    copiado.value = true
    setTimeout(() => (copiado.value = false), 1500)
  } catch {
    notify.error('No se pudo copiar: selecciónela a mano.')
  }
}
</script>
