<!--
  Configuración de la bitácora diaria (`/configuracion/bitacora-diaria`,
  ADR-051): encenderla, nivel de detalle y familias, a quién se le sigue
  (todos o solo los seleccionados por usuario, rol, empresa o estación, con
  fechas opcionales), retención del detalle y del resumen, hora del resumen y
  límites. La bitácora de negocio sigue para todos aunque esta se apague.
  Capacidad `bitacora.configurar`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar>
      <template #start>
        <PageHead title="" />
      </template>
    </Toolbar>

    <Skeleton v-if="!config" height="24rem" class="mx-2" />
    <div v-else class="grid grid-cols-1 gap-4 px-2 @5xl:grid-cols-2">
      <section class="flex flex-col gap-5" aria-label="Qué se registra">
        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="bitacora-activa" class="font-medium">Registrar la actividad de los usuarios</label>
            <p class="m-0 text-xs text-muted-color">Apagada, solo queda la bitácora de negocio (ventas, boletos, salidas, accesos…), que sigue para todos.</p>
          </div>
          <ToggleSwitch v-model="form.activa" input-id="bitacora-activa" />
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-medium">Nivel de detalle</span>
          <SelectButton v-model="form.nivel" :options="config.catalogo.niveles" option-label="etiqueta" option-value="valor" :allow-empty="false" :disabled="!form.activa" />
          <span class="text-xs text-muted-color">{{ DESCRIPCION_NIVEL[form.nivel] }}</span>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-medium">Familias</span>
          <div class="flex flex-wrap gap-x-4 gap-y-2">
            <label v-for="f in config.catalogo.familias" :key="f.valor" class="flex items-center gap-2 text-sm" :class="{ 'opacity-50': !enNivel(f.valor) }">
              <Checkbox :model-value="enNivel(f.valor) && !form.familiasApagadas.includes(f.valor)" binary :disabled="!form.activa || !enNivel(f.valor)" @update:model-value="alternarFamilia(f.valor, $event)" />
              {{ f.etiqueta }}
            </label>
          </div>
          <span class="text-xs text-muted-color">Una familia que el nivel no incluye no se registra. Se puede apagar una que sí incluye.</span>
        </div>

        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="bitacora-cambios" class="font-medium">Cambios campo a campo de todo lo demás</label>
            <p class="m-0 text-xs text-muted-color">Buses, rutas, tarifas, empresas…: qué campo cambió, de qué valor a cuál. Va a la bitácora de negocio.</p>
          </div>
          <ToggleSwitch v-model="form.cambiosEntidades" input-id="bitacora-cambios" />
        </div>

        <label class="flex flex-col gap-1.5">
          <span class="font-medium">Campos extra del cuerpo de las peticiones</span>
          <AutoComplete v-model="form.camposExtra" multiple :typeahead="false" placeholder="Escribí el nombre y Enter" fluid :disabled="form.nivel !== 'maximo'" />
          <span class="text-xs text-muted-color">En nivel máximo se guardan los campos de la lista base ({{ config.catalogo.camposBase.length }}) más estos. Contraseñas, tokens y tarjetas nunca se guardan.</span>
        </label>
      </section>

      <section class="flex flex-col gap-5" aria-label="A quién se le sigue">
        <div class="flex flex-col gap-1.5">
          <span class="font-medium">A quién se le sigue</span>
          <SelectButton v-model="form.alcance" :options="alcances" option-label="label" option-value="value" :allow-empty="false" :disabled="!form.activa" />
        </div>

        <template v-if="form.alcance === 'seleccionados'">
          <div class="grid grid-cols-2 items-end gap-2 @2xl:grid-cols-[8rem_minmax(0,1fr)_9rem_9rem_auto]">
            <label class="flex flex-col gap-1">
              <span class="text-xs">Tipo</span>
              <Select v-model="nueva.tipo" :options="tiposSeleccion" option-label="label" option-value="value" size="small" fluid />
            </label>
            <label class="col-span-2 flex flex-col gap-1 @2xl:col-span-1">
              <span class="text-xs">{{ tiposSeleccion.find((t) => t.value === nueva.tipo)?.label }}</span>
              <AutoComplete v-if="nueva.tipo === 'usuario'" v-model="nueva.usuario" :suggestions="sugerencias" option-label="nombre" force-selection :delay="300" placeholder="Buscar usuario…" size="small" fluid @complete="buscar($event.query)">
                <template #option="{ option }">{{ option.nombre }} <span class="text-xs text-muted-color">@{{ option.username }}</span></template>
              </AutoComplete>
              <Select v-else v-model="nueva.id" :options="opcionesDe(nueva.tipo)" option-label="nombre" option-value="id" filter size="small" fluid placeholder="Elegí…" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs">Desde (opcional)</span>
              <DatePicker v-model="nueva.desde" date-format="dd/mm/yy" size="small" show-button-bar fluid />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs">Hasta (opcional)</span>
              <DatePicker v-model="nueva.hasta" date-format="dd/mm/yy" size="small" show-button-bar fluid :min-date="nueva.desde ?? undefined" />
            </label>
            <Button label="Agregar" size="small" :disabled="!idNueva" @click="agregar"><template #icon><icon name="add" class="mr-1" /></template></Button>
          </div>

          <p v-if="!form.seleccion.length" class="m-0 text-sm text-muted-color">Todavía no hay nadie seleccionado: no se registrará la actividad de nadie.</p>
          <ul v-else class="m-0 flex list-none flex-col gap-1 p-0">
            <li v-for="(e, i) in form.seleccion" :key="`${e.tipo}-${e.id}-${e.desde}-${e.hasta}`" class="flex items-center justify-between gap-2 rounded-md bg-surface-100 px-3 py-1.5 text-sm dark:bg-surface-800">
              <span class="min-w-0 truncate">
                <Tag :value="tiposSeleccion.find((t) => t.value === e.tipo)?.label" severity="secondary" class="mr-2" />{{ e.nombre ?? `${e.tipo} ${e.id}` }}
                <span v-if="e.desde || e.hasta" class="text-xs text-muted-color"> · {{ vigencia(e) }}</span>
              </span>
              <Button text rounded size="small" severity="danger" :aria-label="`Quitar ${e.nombre ?? e.id}`" @click="form.seleccion.splice(i, 1)"><template #icon><icon name="close" /></template></Button>
            </li>
          </ul>
          <span class="text-xs text-muted-color">Un rol o empresa incluye a todos sus usuarios; una estación, a quienes trabajan en ella. Las fechas incluyen el día.</span>
        </template>
        <p v-else class="m-0 text-sm text-muted-color">Se registra la actividad de todos los usuarios.</p>
      </section>

      <section class="flex flex-col gap-5" aria-label="Retención y tiempos">
        <div class="grid grid-cols-1 gap-4 @2xl:grid-cols-2">
          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Meses que se guarda el detalle</span>
            <InputNumber v-model="form.retencionMeses" :min="1" :max="60" show-buttons fluid />
            <span class="text-xs text-muted-color">Pasado ese tiempo se borra el mes entero.</span>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Meses que se guarda el resumen diario</span>
            <InputNumber v-model="form.retencionResumenMeses" :min="0" :max="600" show-buttons fluid />
            <span class="text-xs text-muted-color">0 = sin límite (una fila por usuario y día).</span>
          </label>
        </div>
        <label class="flex items-center gap-2 text-sm">
          <Checkbox v-model="form.exportarAntesDeBorrar" binary input-id="bitacora-exportar" />
          Antes de borrar un mes, guardarlo en un archivo CSV comprimido en el servidor
        </label>
        <div class="grid grid-cols-1 gap-4 @2xl:grid-cols-3">
          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Hora del resumen</span>
            <InputMask v-model="form.horaResumen" mask="99:99" placeholder="00:15" :invalid="!horaOk" fluid />
            <span class="text-xs text-muted-color">Desde esta hora se resume el día anterior.</span>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Envío de pantallas (s)</span>
            <InputNumber v-model="form.intervaloPantallaSegundos" :min="10" :max="600" show-buttons fluid :disabled="form.nivel !== 'maximo'" />
            <span class="text-xs text-muted-color">Cada cuánto la aplicación manda las acciones de pantalla.</span>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Detalle máximo (KB)</span>
            <InputNumber v-model="form.maxDetalleKb" :min="1" :max="64" show-buttons fluid />
            <span class="text-xs text-muted-color">Más grande, se guarda solo un resumen.</span>
          </label>
        </div>
      </section>

      <section class="flex flex-col gap-3 self-start">
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <Message v-if="form.activa && form.alcance === 'seleccionados' && !form.seleccion.length" severity="warn" :closable="false">Con «solo seleccionados» hay que elegir al menos un usuario, rol, empresa o estación.</Message>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span v-if="config.actualizadaEn" class="text-xs text-muted-color">Último cambio: {{ fechaHora(config.actualizadaEn) }}<template v-if="config.actualizadaPor"> por {{ config.actualizadaPor }}</template></span>
          <span v-else />
          <div class="flex gap-2">
            <Button label="Descartar" text :disabled="!cambio || guardando" @click="aplicar(config)" />
            <Button label="Guardar" :loading="guardando" :disabled="!cambio || !horaOk" @click="guardar"><template #icon><icon name="save-outline" class="mr-1" /></template></Button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { buscarUsuarios, fetchConfiguracionBitacora, guardarConfiguracionBitacora, mensajeDeError } from '@/core/bitacora-diaria/api'
import { desdeDiaIso, diaIso } from '@/core/bitacora-diaria/formato'
import type { CambiosBitacora, ConfiguracionBitacora, EntradaSeleccion, NivelActividad, Opcion, TipoActividad, TipoSeleccion, UsuarioBuscado } from '@/core/bitacora-diaria/types'
import { notify } from '@/core/notify'

type Formulario = Required<Omit<CambiosBitacora, 'seleccion'>> & { seleccion: EntradaSeleccion[] }

const DESCRIPCION_NIVEL: Record<NivelActividad, string> = {
  minimo: 'Sesiones y peticiones que cambian algo (crear, guardar, anular…). Lo más liviano.',
  medio: 'Además, cada consulta y las consultas automáticas (contadas por minuto, no una por una).',
  maximo: 'Además, las pantallas que abre y cuánto tiempo, lo que imprime y los datos enviados (sin secretos).',
}

const alcances = [
  { label: 'Todos los usuarios', value: 'todos' },
  { label: 'Solo seleccionados', value: 'seleccionados' },
]
const tiposSeleccion: Array<{ label: string; value: TipoSeleccion }> = [
  { label: 'Usuario', value: 'usuario' },
  { label: 'Rol', value: 'rol' },
  { label: 'Empresa', value: 'empresa' },
  { label: 'Estación', value: 'estacion' },
]

const config = ref<ConfiguracionBitacora | null>(null)
const form = reactive<Formulario>({
  activa: true,
  nivel: 'medio',
  familiasApagadas: [],
  alcance: 'todos',
  seleccion: [],
  retencionMeses: 2,
  exportarAntesDeBorrar: true,
  retencionResumenMeses: 0,
  horaResumen: '00:15',
  intervaloPantallaSegundos: 30,
  maxDetalleKb: 4,
  camposExtra: [],
  cambiosEntidades: true,
})
const guardando = ref(false)
const error = ref('')
const sugerencias = ref<UsuarioBuscado[]>([])
const nueva = reactive<{ tipo: TipoSeleccion; id: number | null; usuario: UsuarioBuscado | null; desde: Date | null; hasta: Date | null }>({
  tipo: 'usuario',
  id: null,
  usuario: null,
  desde: null,
  hasta: null,
})

const horaOk = computed(() => /^([01]\d|2[0-3]):[0-5]\d$/.test(form.horaResumen))
const enNivel = (f: TipoActividad) => config.value?.catalogo.niveles.find((n) => n.valor === form.nivel)?.familias.includes(f) ?? false
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
const formatoDia = (dia: string) => desdeDiaIso(dia)?.toLocaleDateString('es-GT') ?? dia

function vigencia(e: EntradaSeleccion): string {
  if (e.desde && e.hasta) return `del ${formatoDia(e.desde)} al ${formatoDia(e.hasta)}`
  return e.desde ? `desde el ${formatoDia(e.desde)}` : `hasta el ${formatoDia(e.hasta!)}`
}

function opcionesDe(tipo: TipoSeleccion): Opcion[] {
  const c = config.value?.catalogo
  return tipo === 'rol' ? (c?.roles ?? []) : tipo === 'empresa' ? (c?.empresas ?? []) : tipo === 'estacion' ? (c?.estaciones ?? []) : []
}

const idNueva = computed(() => (nueva.tipo === 'usuario' ? (nueva.usuario?.id ?? null) : nueva.id))

watch(
  () => nueva.tipo,
  () => {
    nueva.id = null
    nueva.usuario = null
  },
)

async function buscar(q: string) {
  try {
    sugerencias.value = await buscarUsuarios(q)
  } catch {
    sugerencias.value = []
  }
}

function agregar() {
  const id = idNueva.value
  if (!id) return
  const nombre = nueva.tipo === 'usuario' ? nueva.usuario?.nombre : opcionesDe(nueva.tipo).find((o) => o.id === id)?.nombre
  form.seleccion.push({ tipo: nueva.tipo, id, desde: nueva.desde ? diaIso(nueva.desde) : null, hasta: nueva.hasta ? diaIso(nueva.hasta) : null, nombre })
  nueva.id = null
  nueva.usuario = null
  nueva.desde = null
  nueva.hasta = null
}

function alternarFamilia(f: TipoActividad, encendida: boolean) {
  form.familiasApagadas = encendida ? form.familiasApagadas.filter((x) => x !== f) : [...form.familiasApagadas, f]
}

function cambios(): CambiosBitacora {
  return {
    ...form,
    familiasApagadas: [...form.familiasApagadas],
    camposExtra: [...form.camposExtra],
    seleccion: form.seleccion.map(({ tipo, id, desde, hasta }) => ({ tipo, id, desde, hasta })),
  }
}

/** Lo que se guardaría, comparable con lo guardado (sin los nombres de la selección). */
const firma = (f: Formulario) => JSON.stringify({ ...f, seleccion: f.seleccion.map(({ tipo, id, desde, hasta }) => ({ tipo, id, desde, hasta })) })
const cambio = computed(() => !!config.value && firma(form) !== firma(desdeConfig(config.value)))

function desdeConfig(c: ConfiguracionBitacora): Formulario {
  return {
    activa: c.activa,
    nivel: c.nivel,
    familiasApagadas: [...c.familiasApagadas],
    alcance: c.alcance,
    seleccion: c.seleccion.map((e) => ({ ...e })),
    retencionMeses: c.retencionMeses,
    exportarAntesDeBorrar: c.exportarAntesDeBorrar,
    retencionResumenMeses: c.retencionResumenMeses,
    horaResumen: c.horaResumen,
    intervaloPantallaSegundos: c.intervaloPantallaSegundos,
    maxDetalleKb: c.maxDetalleKb,
    camposExtra: [...c.camposExtra],
    cambiosEntidades: c.cambiosEntidades,
  }
}

function aplicar(c: ConfiguracionBitacora) {
  config.value = c
  Object.assign(form, desdeConfig(c))
  error.value = ''
}

onMounted(async () => {
  try {
    aplicar(await fetchConfiguracionBitacora())
  } catch (e) {
    error.value = mensajeDeError(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(await guardarConfiguracionBitacora(cambios()))
    notify.success('Configuración de la bitácora guardada')
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}
</script>
