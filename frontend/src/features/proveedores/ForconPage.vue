<!--
  Certificador FEL (Forcon) en la administración (`/configuracion/forcon`,
  ADR-047): Forcon o el simulado, URL de la API, usuario y clave globales
  (respaldo de las empresas sin credenciales propias) y emisores en línea.
  La clave nunca llega al navegador: vacía, se conserva la guardada. «Probar»
  consulta un establecimiento en Forcon con lo guardado. Capacidad
  `config.proveedores`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar>
      <template #start>
        <PageHead title="" />
      </template>
    </Toolbar>

    <div class="grid grid-cols-1 gap-4 px-2 @4xl:grid-cols-[minmax(0,36rem)_minmax(0,1fr)]">
      <section class="flex flex-col gap-5">
        <Skeleton v-if="!config" height="20rem" />
        <template v-else>
          <div class="flex items-center justify-between gap-3">
            <div>
              <label for="fel-activo" class="font-medium">Certificar en Forcon</label>
              <p class="m-0 text-xs text-muted-color">Apagado, las facturas las «certifica» el simulado: no tienen valor fiscal.</p>
            </div>
            <ToggleSwitch v-model="form.activo" input-id="fel-activo" />
          </div>
          <Message v-if="form.activo && sinCredenciales.length && !hayGlobal" severity="warn" :closable="false">
            Sin credenciales propias ni usuario global, no se podrá facturar en: {{ sinCredenciales.join(', ') }}.
          </Message>

          <label class="flex flex-col gap-1.5">
            <span class="font-medium">URL de la API</span>
            <InputText v-model.trim="form.url" :invalid="!urlOk" placeholder="https://" fluid />
            <span class="text-xs text-muted-color">
              Pruebas: <a href="#" class="text-primary" @click.prevent="form.url = URL_PRUEBAS_FORCON">{{ URL_PRUEBAS_FORCON }}</a>. La de producción la entrega el soporte de Forcon.
            </span>
          </label>

          <fieldset class="flex flex-col gap-3 rounded-border border border-surface p-3">
            <legend class="px-1 font-medium">Usuario global</legend>
            <p class="m-0 text-xs text-muted-color">Solo para las empresas sin credenciales propias (se cargan con <code>app:fel:credencial</code>). Déjelo vacío si cada empresa tiene las suyas.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Usuario</span>
                <InputText v-model.trim="form.usuario" autocomplete="off" fluid />
              </label>
              <label class="flex flex-col gap-1.5">
                <span class="text-sm">Clave</span>
                <Password
                  v-model="form.clave"
                  :feedback="false"
                  toggle-mask
                  :disabled="!form.usuario"
                  :placeholder="config.conClave && form.usuario ? 'Sin cambios' : ''"
                  :input-props="{ autocomplete: 'new-password' }"
                  fluid
                />
              </label>
            </div>
            <span v-if="config.conClave && form.usuario" class="text-xs text-muted-color">Hay una clave guardada; escriba otra solo para cambiarla.</span>
          </fieldset>

          <fieldset class="flex flex-col gap-3 rounded-border border border-surface p-3">
            <legend class="px-1 font-medium">Emisores en línea</legend>
            <p class="m-0 text-xs text-muted-color">Temporal: las compras en la página de una empresa se facturan a nombre de otra, como en el legado. Vacíelo cuando cada empresa tenga su cuenta.</p>
            <div v-for="(par, i) in form.emisores" :key="i" class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] items-center gap-2">
              <Select v-model="par.empresa" :options="config.empresas" option-label="nombre" option-value="nit" placeholder="Empresa" fluid />
              <icon name="arrow-forward" class="text-muted-color" />
              <Select v-model="par.emisor" :options="config.empresas" option-label="nombre" option-value="nit" placeholder="Factura como" fluid />
              <Button severity="secondary" text rounded aria-label="Quitar" @click="form.emisores.splice(i, 1)"><template #icon><icon name="delete-outline" /></template></Button>
            </div>
            <div>
              <Button label="Agregar" size="small" text @click="form.emisores.push({ empresa: '', emisor: '' })"><template #icon><icon name="add" class="mr-1" /></template></Button>
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
        <section class="flex flex-col gap-3 text-sm">
          <h3 class="m-0 text-sm font-semibold">Probar la conexión</h3>
          <p class="m-0 text-muted-color">Consulta en Forcon los datos de un establecimiento con la URL y las credenciales <b>guardadas</b>. Funciona aunque Forcon esté apagado.</p>
          <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-2">
            <Select v-model="prueba.nit" :options="config?.empresas ?? []" option-label="nombre" option-value="nit" placeholder="Empresa" fluid />
            <InputNumber v-model="prueba.establecimiento" :min="1" :use-grouping="false" placeholder="Estab." fluid />
          </div>
          <div>
            <Button label="Probar" size="small" severity="secondary" :loading="probando" :disabled="!prueba.nit || !prueba.establecimiento" @click="probar">
              <template #icon><icon name="network-check" class="mr-1" /></template>
            </Button>
          </div>
          <Message v-if="resultado" severity="success" :closable="false">
            <b>{{ resultado.razonSocial ?? resultado.nombreComercial }}</b><br />
            {{ resultado.nombreComercial }} · {{ resultado.direccion }}, {{ resultado.municipio }}, {{ resultado.departamento }}
          </Message>
          <Message v-if="errorPrueba" severity="error" :closable="false">{{ errorPrueba }}</Message>
        </section>

        <section v-if="config" class="text-sm">
          <h3 class="m-0 mb-2 text-sm font-semibold">Credenciales por empresa</h3>
          <ul class="m-0 flex flex-col gap-1.5 p-0">
            <li v-for="e in config.empresas" :key="e.nit" class="flex items-center justify-between gap-2">
              <span class="truncate">{{ e.nombre }} <span class="text-muted-color">· {{ e.nit }}</span></span>
              <Tag v-bind="estadoCredencial(e.credencial)" class="shrink-0 whitespace-nowrap" />
            </li>
          </ul>
          <p class="m-0 mt-3 text-xs text-muted-color">Se cargan por consola: <code>app:fel:credencial &lt;nit&gt; &lt;usuario&gt; --probar=1</code>. «Ilegible»: se cifró con otro <code>APP_SECRET</code>; hay que volver a cargarla.</p>
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { fetchForcon, guardarForcon, mensajeDeError, probarForcon, URL_PRUEBAS_FORCON, urlValida } from '@/core/proveedores/api'
import type { ConfiguracionForcon, DatosEstablecimiento, EmisorEnLinea } from '@/core/proveedores/types'
import { notify } from '@/core/notify'

const config = ref<ConfiguracionForcon | null>(null)
const form = reactive({ activo: false, url: '', usuario: '', clave: '', emisores: [] as EmisorEnLinea[] })
const guardando = ref(false)
const error = ref('')

const prueba = reactive({ nit: '' as string, establecimiento: 1 as number | null })
const probando = ref(false)
const resultado = ref<DatosEstablecimiento | null>(null)
const errorPrueba = ref('')

const urlOk = computed(() => urlValida(form.url))
const valido = computed(() => urlOk.value && form.emisores.every((p) => p.empresa && p.emisor && p.empresa !== p.emisor))
const hayGlobal = computed(() => !!config.value?.usuario && config.value.conClave)
const sinCredenciales = computed(() => (config.value?.empresas ?? []).filter((e) => e.credencial !== 'propia').map((e) => e.nombre))
const clavePares = (l: EmisorEnLinea[]) => JSON.stringify(l.map((p) => [p.empresa, p.emisor]))
const cambio = computed(() => {
  const c = config.value
  return (
    !!c &&
    (form.activo !== c.activo ||
      form.url !== c.url ||
      form.usuario !== (c.usuario ?? '') ||
      form.clave !== '' ||
      clavePares(form.emisores) !== clavePares(c.emisoresEnLinea))
  )
})
function estadoCredencial(c: ConfiguracionForcon['empresas'][number]['credencial']) {
  if (c === 'propia') return { value: 'Propias', severity: 'success' }
  if (c === 'ilegible') return { value: 'Ilegible', severity: 'danger' }
  return hayGlobal.value ? { value: 'Usa la global', severity: 'info' } : { value: 'Sin credenciales', severity: 'warn' }
}
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

function aplicar(c: ConfiguracionForcon) {
  config.value = c
  form.activo = c.activo
  form.url = c.url
  form.usuario = c.usuario ?? ''
  form.clave = ''
  form.emisores = c.emisoresEnLinea.map((p) => ({ ...p }))
  prueba.nit ||= c.empresas[0]?.nit ?? ''
}

onMounted(async () => {
  try {
    aplicar(await fetchForcon())
  } catch (e) {
    error.value = mensajeDeError(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(await guardarForcon({ activo: form.activo, url: form.url, usuario: form.usuario, clave: form.clave, emisoresEnLinea: form.emisores }))
    notify.success('Configuración de Forcon guardada')
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

async function probar() {
  probando.value = true
  resultado.value = null
  errorPrueba.value = ''
  try {
    resultado.value = await probarForcon(prueba.nit, prueba.establecimiento ?? 1)
  } catch (e) {
    errorPrueba.value = mensajeDeError(e)
  } finally {
    probando.value = false
  }
}
</script>
