<!--
  Bitácora diaria de los usuarios (`/auditoria/bitacora-diaria`, ADR-051):
  quiénes trabajaron el día elegido con su resumen (tiempo activo,
  peticiones, cambios, errores) y, al elegir uno, su línea de tiempo: sesión,
  peticiones, pantallas y lo que cambió en el negocio, con cada cambio junto a
  la petición que lo hizo. Fecha y usuario quedan en la URL. Capacidad
  `bitacora.usuarios`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <div class="flex items-center gap-2">
          <DatePicker v-model="fecha" date-format="dd/mm/yy" show-icon icon-display="input" :manual-input="false" :max-date="hoy" input-id="bitacora-fecha" aria-label="Día" />
          <Button text rounded aria-label="Actualizar" v-tooltip.bottom="'Actualizar'" :loading="cargandoDia" @click="cargarDia"><template #icon><icon name="refresh" /></template></Button>
        </div>
      </template>
    </Toolbar>

    <Message v-if="error" severity="error" :closable="false" class="mx-2">{{ error }}</Message>

    <div class="grid gap-4 px-2 @5xl:grid-cols-[minmax(0,26rem)_1fr]">
      <section class="panel flex flex-col gap-3 !p-3" aria-label="Usuarios con actividad">
        <div class="flex items-center justify-between gap-2">
          <h3 class="m-0 text-sm font-semibold">Usuarios con actividad</h3>
          <Tag v-if="dia" :value="dia.guardado ? 'Día cerrado' : 'En curso'" :severity="dia.guardado ? 'secondary' : 'info'" />
        </div>
        <IconField>
          <InputIcon><icon name="search" /></InputIcon>
          <InputText v-model.trim="buscar" placeholder="Buscar usuario" fluid size="small" />
        </IconField>
        <Skeleton v-if="cargandoDia && !dia" height="12rem" />
        <p v-else-if="dia && !usuarios.length" class="m-0 text-sm text-muted-color">Nadie tuvo actividad registrada este día.</p>
        <ul v-else class="m-0 flex list-none flex-col gap-1 p-0">
          <li v-for="u in usuarios" :key="u.usuarioId">
            <button type="button" class="bitacora-usuario" :class="{ 'bitacora-usuario--activo': u.usuarioId === usuarioId }" @click="usuarioId = u.usuarioId">
              <span class="flex min-w-0 flex-col items-start">
                <span class="truncate font-medium">{{ u.nombre }}</span>
                <span class="text-xs text-muted-color">{{ u.primera ? hora(u.primera).slice(0, 5) : '—' }}–{{ u.ultima ? hora(u.ultima).slice(0, 5) : '—' }} · {{ duracion(u.minutosActivos) }} activo</span>
              </span>
              <span class="flex shrink-0 items-center gap-1 text-xs">
                <Tag v-if="u.cambios" :value="`${u.cambios} cambios`" severity="success" />
                <Tag v-if="u.errores" :value="`${u.errores} errores`" severity="danger" />
              </span>
            </button>
          </li>
        </ul>
      </section>

      <section class="panel flex min-w-0 flex-col gap-3 !p-3" aria-label="Línea de tiempo">
        <p v-if="!usuarioId" class="m-0 text-sm text-muted-color">Elegí un usuario para ver todo lo que hizo ese día.</p>
        <Skeleton v-else-if="cargandoUsuario && !detalle" height="20rem" />
        <template v-else-if="detalle">
          <header class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="m-0 text-base font-semibold">{{ detalle.usuario.nombre ?? detalle.usuario.username ?? `Usuario ${detalle.usuario.id}` }}</h3>
            <span v-if="detalle.usuario.username" class="text-xs text-muted-color">@{{ detalle.usuario.username }}</span>
          </header>

          <div v-if="detalle.resumen" class="grid grid-cols-2 gap-2 @2xl:grid-cols-4">
            <div v-for="c in cifras" :key="c.etiqueta" class="rounded-md bg-surface-100 px-3 py-2 dark:bg-surface-800">
              <div class="text-xs text-muted-color">{{ c.etiqueta }}</div>
              <div class="text-lg font-semibold" :class="{ 'text-red-500': c.alerta }">{{ c.valor }}</div>
            </div>
          </div>
          <ul v-if="operaciones.length" class="m-0 flex list-none flex-wrap gap-1.5 p-0 text-xs">
            <li v-for="[op, n] in operaciones" :key="op" class="rounded-full bg-surface-100 px-2.5 py-1 dark:bg-surface-800">{{ op }}: {{ n }}</li>
          </ul>

          <div class="flex flex-wrap items-center gap-3">
            <SelectButton v-model="tipos" :options="opcionesTipo" option-label="label" option-value="value" multiple size="small" aria-label="Qué mostrar" />
            <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="soloErrores" input-id="bitacora-errores" /> Solo errores</label>
            <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="sinConsultas" input-id="bitacora-consultas" /> Ocultar consultas</label>
          </div>

          <Message v-if="detalle.cortada" severity="warn" :closable="false">El día tiene más entradas de las que se muestran; se ven las primeras {{ detalle.entradas.length }}.</Message>
          <p v-if="!grupos.length" class="m-0 text-sm text-muted-color">No hay entradas con estos filtros.</p>
          <ol v-else class="bitacora-linea m-0 list-none p-0">
            <li v-for="g in grupos" :key="g.clave" class="bitacora-entrada" :class="{ 'bitacora-entrada--error': esError(g.principal) }">
              <time class="bitacora-entrada__hora" :datetime="g.principal.fecha">{{ hora(g.principal.fecha) }}</time>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <Tag :value="etiquetaTipo(g.principal)" :severity="severidad(g.principal)" class="text-[0.65rem]" />
                  <span class="font-medium">{{ titulo(g.principal) }}</span>
                  <span v-if="g.principal.origen === 'actividad' && g.principal.estado" class="text-xs" :class="g.principal.estado >= 400 ? 'text-red-500' : 'text-muted-color'">{{ g.principal.estado }}</span>
                  <span v-if="g.principal.origen === 'actividad' && g.principal.duracionMs !== null && g.principal.tipo === 'peticion'" class="text-xs text-muted-color">{{ milisegundos(g.principal.duracionMs) }}</span>
                </div>
                <ul v-if="lineas(g.principal).length" class="m-0 list-none p-0 text-xs text-muted-color">
                  <li v-for="(l, i) in lineas(g.principal)" :key="i" class="break-words">{{ l }}</li>
                </ul>
                <ul v-if="g.efectos.length" class="m-0 mt-1 flex list-none flex-col gap-1 border-l-2 border-green-500/50 p-0 pl-3">
                  <li v-for="(ef, i) in g.efectos" :key="i" class="text-sm">
                    <span>{{ titulo(ef) }}</span>
                    <ul v-if="lineas(ef).length" class="m-0 list-none p-0 text-xs text-muted-color">
                      <li v-for="(l, j) in lineas(ef)" :key="j" class="break-words">{{ l }}</li>
                    </ul>
                  </li>
                </ul>
              </div>
            </li>
          </ol>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { fetchDia, fetchDiaDeUsuario, mensajeDeError } from '@/core/bitacora-diaria/api'
import { agrupar, desdeDiaIso, diaIso, duracion, esError, hora, lineas, milisegundos, titulo } from '@/core/bitacora-diaria/formato'
import type { DiaBitacora, DiaDeUsuario, EntradaDiaria } from '@/core/bitacora-diaria/types'

type Filtro = 'sesion' | 'peticion' | 'pantalla' | 'sondeo' | 'negocio'

const route = useRoute()
const router = useRouter()
const hoy = new Date()

const fecha = ref<Date>(desdeDiaIso(route.query.fecha as string | undefined) ?? hoy)
const usuarioId = ref<number | null>(Number(route.query.usuario) || null)
const dia = ref<DiaBitacora | null>(null)
const detalle = ref<DiaDeUsuario | null>(null)
const cargandoDia = ref(false)
const cargandoUsuario = ref(false)
const error = ref('')
const buscar = ref('')
const tipos = ref<Filtro[]>(['sesion', 'peticion', 'pantalla', 'negocio'])
const soloErrores = ref(false)
const sinConsultas = ref(false)

const opcionesTipo: Array<{ label: string; value: Filtro }> = [
  { label: 'Sesión', value: 'sesion' },
  { label: 'Peticiones', value: 'peticion' },
  { label: 'Pantallas', value: 'pantalla' },
  { label: 'Consultas automáticas', value: 'sondeo' },
  { label: 'Negocio', value: 'negocio' },
]
const ETIQUETAS: Record<string, string> = { sesion: 'Sesión', peticion: 'Petición', pantalla: 'Pantalla', sondeo: 'Automática', negocio: 'Negocio' }

const fechaIso = computed(() => diaIso(fecha.value))

const usuarios = computed(() => {
  const q = buscar.value.toLowerCase()
  return (dia.value?.usuarios ?? []).filter((u) => !q || u.nombre.toLowerCase().includes(q) || (u.username ?? '').toLowerCase().includes(q))
})

const cifras = computed(() => {
  const r = detalle.value?.resumen
  if (!r) return []
  return [
    { etiqueta: 'Tiempo activo', valor: duracion(r.minutosActivos) },
    { etiqueta: 'Primera / última', valor: `${r.primera ? hora(r.primera).slice(0, 5) : '—'} – ${r.ultima ? hora(r.ultima).slice(0, 5) : '—'}` },
    { etiqueta: 'Peticiones', valor: String(r.peticiones) },
    { etiqueta: 'Escrituras', valor: String(r.escrituras) },
    { etiqueta: 'Cambios de negocio', valor: String(r.cambios) },
    { etiqueta: 'Pantallas', valor: String(r.pantallas) },
    { etiqueta: 'Sesiones', valor: String(r.sesiones) },
    { etiqueta: 'Errores', valor: String(r.errores), alerta: r.errores > 0 },
  ]
})

const operaciones = computed(() => Object.entries(detalle.value?.resumen?.operaciones ?? {}).sort((a, b) => b[1] - a[1]))

function visible(e: EntradaDiaria): boolean {
  if (!tipos.value.includes(e.origen === 'negocio' ? 'negocio' : e.tipo)) return false
  if (soloErrores.value && !esError(e)) return false
  if (sinConsultas.value && e.origen === 'actividad' && e.tipo === 'peticion' && (e.metodo === 'GET' || e.accion?.startsWith('query '))) return false
  return true
}

/** Los cambios de negocio se agrupan con su petición aunque la petición quede oculta por los filtros. */
const grupos = computed(() => agrupar(detalle.value?.entradas ?? []).filter((g) => visible(g.principal) || (tipos.value.includes('negocio') && g.efectos.length > 0 && !soloErrores.value)))

const etiquetaTipo = (e: EntradaDiaria) => ETIQUETAS[e.origen === 'negocio' ? 'negocio' : e.tipo] ?? e.tipo
const severidad = (e: EntradaDiaria) => (esError(e) ? 'danger' : e.origen === 'negocio' ? 'success' : e.tipo === 'sesion' ? 'info' : e.tipo === 'pantalla' ? 'warn' : 'secondary')

async function cargarDia() {
  cargandoDia.value = true
  error.value = ''
  try {
    dia.value = await fetchDia(fechaIso.value)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargandoDia.value = false
  }
  if (usuarioId.value) await cargarUsuario()
}

async function cargarUsuario() {
  const id = usuarioId.value
  if (!id) return (detalle.value = null)
  cargandoUsuario.value = true
  try {
    const datos = await fetchDiaDeUsuario(id, fechaIso.value)
    if (usuarioId.value === id) detalle.value = datos
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargandoUsuario.value = false
  }
}

watch(fechaIso, () => void cargarDia())
watch(usuarioId, () => {
  detalle.value = null
  void cargarUsuario()
})
watch([fechaIso, usuarioId], ([f, u]) => {
  void router.replace({ query: { ...route.query, fecha: f, usuario: u ? String(u) : undefined } })
})

onMounted(cargarDia)
</script>

<style scoped>
.bitacora-usuario {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0.625rem;
  border-radius: var(--p-border-radius-md, 6px);
  text-align: left;
  background: transparent;
  cursor: pointer;
}
.bitacora-usuario:hover {
  background: var(--p-content-hover-background);
}
.bitacora-usuario--activo {
  background: var(--p-highlight-background);
  color: var(--p-highlight-color);
}
.bitacora-entrada {
  display: grid;
  grid-template-columns: 4.5rem 1fr;
  gap: 0.75rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--p-content-border-color);
}
.bitacora-entrada--error {
  background: color-mix(in srgb, var(--p-red-500) 6%, transparent);
}
.bitacora-entrada__hora {
  font-variant-numeric: tabular-nums;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
</style>
