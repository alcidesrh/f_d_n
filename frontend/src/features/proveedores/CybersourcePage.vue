<!--
  Pasarela de pago (Cybersource) en la administración
  (`/configuracion/cybersource`, ADR-047): Cybersource o la simulada, URL de
  la API y Org ID de la huella del dispositivo. El comercio de cada empresa
  se carga por consola (`app:pago:credencial`). Capacidad `config.proveedores`.
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
        <Skeleton v-if="!config" height="16rem" />
        <template v-else>
          <div class="flex items-center justify-between gap-3">
            <div>
              <label for="pago-activo" class="font-medium">Cobrar con Cybersource</label>
              <p class="m-0 text-xs text-muted-color">Apagada, los pagos con tarjeta de la página van a la pasarela simulada: no se cobra nada.</p>
            </div>
            <ToggleSwitch v-model="form.activo" input-id="pago-activo" />
          </div>
          <Message v-if="form.activo && sinComercio.length" severity="warn" :closable="false">
            Sin comercio cargado, la página no podrá cobrar las salidas de: {{ sinComercio.join(', ') }}.
          </Message>

          <div class="flex flex-col gap-1.5">
            <span class="font-medium">Ambiente</span>
            <SelectButton v-model="ambiente" :options="ambientes" option-label="label" option-value="value" :allow-empty="false" />
            <InputText v-if="ambiente === 'otra'" v-model.trim="form.url" :invalid="!urlOk" placeholder="https://" fluid />
            <span class="text-xs text-muted-color">{{ form.url }}</span>
          </div>

          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Org ID de ThreatMetrix</span>
            <InputText v-model.trim="form.orgId" placeholder="Vacío: sin huella del dispositivo" fluid />
            <span class="text-xs text-muted-color">Con él, la página carga el script de huella del dispositivo y el cobro envía <code>fingerprintSessionId</code> (Decision Manager).</span>
          </label>

          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span v-if="config.actualizadaEn" class="text-xs text-muted-color">Último cambio: {{ fechaHora(config.actualizadaEn) }}<template v-if="config.actualizadaPor"> por {{ config.actualizadaPor }}</template></span>
            <span v-else />
            <Button label="Guardar" :loading="guardando" :disabled="!cambio || !urlOk" @click="guardar"><template #icon><icon name="save-outline" class="mr-1" /></template></Button>
          </div>
        </template>
      </section>

      <aside v-if="config" class="text-sm">
        <h3 class="m-0 mb-2 text-sm font-semibold">Comercio por empresa</h3>
        <ul class="m-0 flex flex-col gap-1.5 p-0">
          <li v-for="e in config.empresas" :key="e.nombre" class="flex items-center justify-between gap-2">
            <span class="truncate">{{ e.nombre }}</span>
            <Tag :value="e.comercio ?? 'Sin comercio'" :severity="e.comercio ? 'success' : 'secondary'" class="shrink-0 whitespace-nowrap" />
          </li>
        </ul>
        <p class="m-0 mt-3 text-xs text-muted-color">Se cargan por consola: <code>app:pago:credencial &lt;nit&gt; &lt;merchant id&gt; &lt;key id&gt;</code> (la llave secreta se pide oculta).</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { fetchCybersource, guardarCybersource, mensajeDeError, URLS_CYBERSOURCE, urlValida } from '@/core/proveedores/api'
import type { ConfiguracionCybersource } from '@/core/proveedores/types'
import { notify } from '@/core/notify'

type Ambiente = 'pruebas' | 'produccion' | 'otra'

const config = ref<ConfiguracionCybersource | null>(null)
const form = reactive({ activo: false, url: '', orgId: '' })
const guardando = ref(false)
const error = ref('')

const ambientes: Array<{ label: string; value: Ambiente }> = [
  { label: 'Pruebas', value: 'pruebas' },
  { label: 'Producción', value: 'produccion' },
  { label: 'Otra URL', value: 'otra' },
]
const ambienteDe = (url: string): Ambiente =>
  url === URLS_CYBERSOURCE.pruebas ? 'pruebas' : url === URLS_CYBERSOURCE.produccion ? 'produccion' : 'otra'
const ambiente = ref<Ambiente>('pruebas')
watch(ambiente, (a) => {
  if (a !== 'otra') form.url = URLS_CYBERSOURCE[a]
})

const urlOk = computed(() => urlValida(form.url))
const sinComercio = computed(() => (config.value?.empresas ?? []).filter((e) => !e.comercio).map((e) => e.nombre))
const cambio = computed(() => {
  const c = config.value
  return !!c && (form.activo !== c.activo || form.url !== c.url || form.orgId !== (c.orgId ?? ''))
})
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

function aplicar(c: ConfiguracionCybersource) {
  config.value = c
  form.activo = c.activo
  form.url = c.url
  form.orgId = c.orgId ?? ''
  ambiente.value = ambienteDe(c.url)
}

onMounted(async () => {
  try {
    aplicar(await fetchCybersource())
  } catch (e) {
    error.value = mensajeDeError(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(await guardarCybersource({ activo: form.activo, url: form.url, orgId: form.orgId }))
    notify.success('Configuración de Cybersource guardada')
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}
</script>
