<!--
  Mensajes predeterminados (`/configuracion/mensajes-predeterminados`,
  ADR-045): las instancias que mandan solas a «Bitácora del sistema» de su
  audiencia. Puede haber varias del mismo tipo (dos rutas con «Salida
  iniciada»). Activar o desactivar, probar (llega solo a quien prueba),
  editar y eliminar. Solo con `chat.predeterminados` (ROLE_SUPER_ADMIN y
  ROLE_ADMIN).
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <Button label="Nuevo mensaje" size="small" :disabled="!opciones" @click="abrir(null)">
          <template #icon><icon name="add" class="mr-1" /></template>
        </Button>
      </template>
    </Toolbar>

    <p class="m-0 px-2 text-sm text-muted-color">Llegan a «Bitácora del sistema» en el chat de cada destinatario: al iniciar una salida, al cierre del día, a la hora que elija o cuando la revisión de la madrugada encuentra una anomalía.</p>

    <Message v-if="error" severity="warn" :closable="false" class="mx-2">{{ error }}</Message>

    <div v-if="cargando" class="flex justify-center py-10"><ProgressSpinner style="width: 2rem; height: 2rem" /></div>

    <div v-else-if="!mensajes.length && !error" class="card mx-2 flex flex-col items-center gap-2 py-10 text-center text-muted-color">
      <icon name="campaign-outline" size="2rem" />
      <p class="m-0">Todavía no hay mensajes predeterminados.</p>
      <Button label="Crear el primero" size="small" text :disabled="!opciones" @click="abrir(null)" />
    </div>

    <ul v-else class="lista">
      <li v-for="m in mensajes" :key="m.id" class="card tarjeta" :class="{ 'tarjeta--inactiva': !m.activo }">
        <header class="flex min-w-0 items-start gap-3">
          <icon :name="icono(m.tipo)" class="mt-0.5 text-xl text-primary" />
          <div class="min-w-0 flex-1">
            <h2 class="m-0 truncate text-base font-semibold">{{ m.nombre }}</h2>
            <span class="text-sm text-muted-color">{{ m.tipo === 'libre' ? 'Mensaje propio' : m.tipoNombre }}<template v-if="m.empresa"> · {{ m.empresa.nombre }}</template></span>
          </div>
          <ToggleSwitch :model-value="m.activo" :disabled="cambiando === m.id" :aria-label="m.activo ? `Desactivar ${m.nombre}` : `Activar ${m.nombre}`" v-tooltip.left="m.activo ? 'Activa' : 'Inactiva'" @update:model-value="(v: boolean) => activar(m, v)" />
        </header>

        <dl class="datos">
          <div><dt><icon name="schedule" /></dt><dd>{{ cuandoSale(m, opciones?.horaCierre ?? '22:00') }}</dd></div>
          <div><dt><icon name="group-outline" /></dt><dd>{{ textoAudiencia(m) }}</dd></div>
          <div v-if="m.texto || m.archivos.length">
            <dt><icon name="chat-outline" /></dt>
            <dd class="truncate">{{ contenido(m) }}</dd>
          </div>
          <div><dt><icon name="send-outline" /></dt><dd>{{ m.ultimoEnvio ? `Último envío ${fecha(m.ultimoEnvio)} · ${m.envios} en total` : 'Aún no se ha enviado' }}</dd></div>
        </dl>

        <footer class="flex flex-wrap justify-end gap-1">
          <Button label="Probar" size="small" text :loading="probando === m.id" v-tooltip.top="'Le llega solo a usted, ahora'" @click="probar(m)">
            <template #icon><icon name="science-outline" class="mr-1" /></template>
          </Button>
          <Button label="Editar" size="small" text @click="abrir(m)">
            <template #icon><icon name="edit-outline" class="mr-1" /></template>
          </Button>
          <Button label="Eliminar" size="small" text severity="danger" @click="eliminar(m)">
            <template #icon><icon name="delete-outline" class="mr-1" /></template>
          </Button>
        </footer>
      </li>
    </ul>

    <MensajePredeterminadoDialog v-if="opciones" v-model:visible="dialogo" :mensaje="editando" :opciones="opciones" @guardado="guardado" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import {
  activarPredeterminado,
  cuandoSale,
  eliminarPredeterminado,
  fetchPredeterminados,
  probarPredeterminado,
  textoAudiencia,
  type CodigoTipo,
  type MensajePredeterminado,
  type OpcionesPredeterminados,
} from '@/core/chat/predeterminados'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import MensajePredeterminadoDialog from './MensajePredeterminadoDialog.vue'

const confirm = useConfirm()

const mensajes = ref<MensajePredeterminado[]>([])
const opciones = ref<OpcionesPredeterminados | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)
const dialogo = ref(false)
const editando = ref<MensajePredeterminado | null>(null)
const cambiando = ref<number | null>(null)
const probando = ref<number | null>(null)

const formato = new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' })
const fecha = (iso: string) => formato.format(new Date(iso))

const ICONOS: Record<CodigoTipo, string> = {
  libre: 'chat-outline',
  salida_iniciada: 'departure-board',
  anomalia_reasignacion: 'swap-horiz',
  anomalia_tarifa: 'sell-outline',
  ventas_dia: 'point-of-sale',
  desempeno_dia: 'monitoring',
}
const icono = (t: CodigoTipo) => ICONOS[t] ?? 'campaign-outline'

const contenido = (m: MensajePredeterminado) =>
  [m.texto, m.archivos.length ? `${m.archivos.length} ${m.archivos.length === 1 ? 'adjunto' : 'adjuntos'}` : null].filter(Boolean).join(' · ')

onMounted(cargar)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const r = await fetchPredeterminados()
    mensajes.value = r.mensajes
    opciones.value = r.opciones
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudieron cargar los mensajes predeterminados.'
  } finally {
    cargando.value = false
  }
}

function abrir(m: MensajePredeterminado | null) {
  editando.value = m
  dialogo.value = true
}

function reemplazar(m: MensajePredeterminado) {
  const i = mensajes.value.findIndex((x) => x.id === m.id)
  if (i === -1) mensajes.value = [...mensajes.value, m].sort((a, b) => a.nombre.localeCompare(b.nombre))
  else mensajes.value.splice(i, 1, { ...mensajes.value[i]!, ...m, ultimoEnvio: mensajes.value[i]!.ultimoEnvio, envios: mensajes.value[i]!.envios })
}

function guardado(m: MensajePredeterminado) {
  reemplazar(m)
}

async function activar(m: MensajePredeterminado, activo: boolean) {
  cambiando.value = m.id
  try {
    reemplazar(await activarPredeterminado(m.id, activo))
  } catch (e) {
    notify.error(e instanceof HttpError ? e.message : 'No se pudo cambiar el mensaje.')
  } finally {
    cambiando.value = null
  }
}

async function probar(m: MensajePredeterminado) {
  probando.value = m.id
  try {
    await probarPredeterminado(m.id)
    notify.success('Le llegó a «Bitácora del sistema» en el chat.')
  } catch (e) {
    notify.error(e instanceof HttpError ? e.message : 'No se pudo enviar la prueba.')
  } finally {
    probando.value = null
  }
}

function eliminar(m: MensajePredeterminado) {
  confirm.require({
    header: 'Eliminar mensaje predeterminado',
    message: `¿Eliminar «${m.nombre}»? Deja de enviarse; lo ya enviado sigue en el chat.`,
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await eliminarPredeterminado(m.id)
        mensajes.value = mensajes.value.filter((x) => x.id !== m.id)
      } catch (e) {
        notify.error(e instanceof HttpError ? e.message : 'No se pudo eliminar.')
      }
    },
  })
}
</script>

<style scoped>
.lista {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0 0.5rem 1rem;
  list-style: none;
}
@container (min-width: 48rem) {
  .lista {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@container (min-width: 80rem) {
  .lista {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
  padding: 0.9rem 1rem 0.5rem;
}
.tarjeta--inactiva {
  opacity: 0.65;
}
.datos {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.85rem;
  > div {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    min-width: 0;
  }
  dt {
    flex-shrink: 0;
    color: var(--p-surface-500);
  }
  dd {
    min-width: 0;
    margin: 0;
  }
}
</style>
