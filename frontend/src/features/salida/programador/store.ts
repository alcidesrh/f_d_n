/**
 * Estado del programador de salidas (ADR-024, ADR-031): opciones (empresas,
 * buses, distribuciones), esquemas guardados, el formulario, la vista previa
 * automática y el resultado de crear. La lógica pura está en
 * `core/salida/programacion` (formulario → payload) y `core/salida/plan`
 * (vista previa y resultado).
 *
 * Tras crear, el panel muestra el resultado (lo creado con su id y lo que se
 * omitió por qué) hasta que el formulario cambia; no se vuelve a pedir la
 * vista previa, que vería lo recién creado como «ya existe».
 */
import { defineStore } from 'pinia'
import { computed, reactive, ref, shallowRef, watch } from 'vue'
import { fetchBuscarRutas } from '@/core/ruta/api'
import { eliminarEsquema, fetchEsquemas, fetchOpciones, guardarEsquema, programar, vistaPrevia } from '@/core/salida/api'
import { busesPara } from '@/core/salida/buses'
import { aDia } from '@/core/salida/filtro'
import {
  aEsquema,
  automatizacion,
  aPayload,
  conEsquema,
  diasProgramados,
  difiereDeEsquema,
  errores as erroresDe,
  formInicial,
  horaValida,
  MAX_MOMENTOS,
  normalizarHora,
  type ProgramadorForm,
} from '@/core/salida/programacion'
import type { Esquema, Momento, OpcionesSalidas, ResultadoProgramacion, RutaOpcion, VistaPrevia } from '@/core/salida/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

/** Espera antes de pedir la vista previa tras un cambio. */
const ESPERA_PREVIA_MS = 400

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e))

export const useProgramadorStore = defineStore('programador-salidas', () => {
  const opciones = shallowRef<OpcionesSalidas | null>(null)
  const sinPermiso = ref(false)

  const form = reactive<ProgramadorForm>(formInicial())
  /** La ruta elegida con sus datos (duración, paradas): se pide por id, no se carga el listado de rutas. */
  const ruta = shallowRef<RutaOpcion | null>(null)

  const esquemas = ref<Esquema[]>([])
  const esquemaId = ref<number | null>(null)
  const guardandoEsquema = ref(false)
  /** Guardar la configuración como esquema nuevo al crear. */
  const guardar = ref(false)
  const nombreEsquema = ref('')

  const previa = shallowRef<VistaPrevia | null>(null)
  const cargandoPrevia = ref(false)
  const errorPrevia = ref('')
  const creando = ref(false)
  /** Lo que devolvió la última creación; mientras exista, el panel muestra el resultado y no la vista previa. */
  const resultado = shallowRef<ResultadoProgramacion | null>(null)

  const esquema = computed(() => esquemas.value.find((e) => e.id === esquemaId.value) ?? null)
  const cambiosEsquema = computed(() => !!esquema.value && difiereDeEsquema(form, esquema.value))
  const dias = computed(() => diasProgramados(form))
  const errores = computed(() => erroresDe(form))

  /** El usuario no tiene empresa (ve toda la flota): puede elegir una para filtrar distribuciones y buses. */
  const pideEmpresa = computed(() => !!opciones.value && opciones.value.miEmpresaId === null && opciones.value.empresas.length > 1)
  const empresas = computed(() => new Map((opciones.value?.empresas ?? []).map((e) => [e.id, e.nombre])))
  const clases = computed(() => new Map((opciones.value?.clases ?? []).map((c) => [c.id, c.nombre])))
  /** Los buses de la empresa elegida (todos si no se eligió). */
  const busesVisibles = computed(() => busesPara(opciones.value?.buses ?? [], { croquisId: null, empresaId: form.empresaId }))
  /** Solo las distribuciones que tiene algún bus de la empresa elegida: siempre hay un bus para asignar. */
  const croquisVisibles = computed(() => {
    const conBus = new Set(busesVisibles.value.map((b) => b.croquisId))
    return (opciones.value?.croquis ?? []).filter((c) => conBus.has(c.id))
  })
  const busDe = (id: number | null) => (id ? (opciones.value?.buses.find((b) => b.id === id) ?? null) : null)

  let pedidoRuta = 0
  watch(
    () => form.rutaId,
    async (id) => {
      const n = ++pedidoRuta
      const r = id === null ? null : ((await fetchBuscarRutas('', [id])).items.find((x) => x.id === id) ?? null)
      if (n === pedidoRuta) ruta.value = r
    },
  )

  // Al cambiar de empresa se quitan los buses de otra empresa y las distribuciones que ella no tiene.
  watch(
    () => form.empresaId,
    (empresaId) => {
      if (!empresaId) return
      const disponibles = new Set(croquisVisibles.value.map((c) => c.id))
      for (const m of form.momentos) {
        if (m.busId && (busDe(m.busId)?.empresaId ?? empresaId) !== empresaId) m.busId = null
        if (m.croquisId && !disponibles.has(m.croquisId)) m.croquisId = null
      }
    },
  )

  watch([guardar, nombreEsquema], ([g, n]) => (form.guardarComo = g ? n : null))

  /** Otra distribución: si el bus elegido no la tiene, se quita. */
  function elegirCroquis(m: Momento, croquisId: number | null) {
    m.croquisId = croquisId
    if (m.busId && busDe(m.busId)?.croquisId !== croquisId) m.busId = null
  }

  /** Elegir un bus fija la distribución a la suya. */
  function elegirBus(m: Momento, busId: number | null) {
    m.busId = busId
    const bus = busDe(busId)
    if (bus?.croquisId) m.croquisId = bus.croquisId
  }

  function agregarHora() {
    if (form.momentos.length >= MAX_MOMENTOS) return
    const ultima = form.momentos[form.momentos.length - 1]
    form.momentos.push({ hora: ultima?.hora ?? '06:00', croquisId: ultima?.croquisId ?? null, busId: null })
  }

  function quitarHora(i: number) {
    if (form.momentos.length > 1) form.momentos.splice(i, 1)
  }

  function ordenarHoras() {
    const clave = (h: string) => (horaValida(h) ? normalizarHora(h) : h)
    form.momentos.sort((a, b) => clave(a.hora).localeCompare(clave(b.hora)))
  }

  /** Carga un esquema guardado en el formulario (null: deja de editar el esquema; el formulario queda como está). */
  function cargarEsquema(id: number | null) {
    esquemaId.value = id
    guardar.value = false
    const e = esquema.value
    if (e) Object.assign(form, conEsquema(form, e, (busId) => busDe(busId)?.croquisId ?? null))
  }

  // Vista previa automática (con espera) cada vez que el formulario es válido y cambia.
  let espera: ReturnType<typeof setTimeout> | null = null
  let pedidoPrevia = 0
  const clavePrevia = computed(() => (errores.value.length ? null : JSON.stringify({ ...aPayload(form), guardarComo: undefined })))
  watch(clavePrevia, (clave) => {
    if (espera) clearTimeout(espera)
    resultado.value = null
    if (clave) espera = setTimeout(cargarPrevia, ESPERA_PREVIA_MS)
  })

  async function cargarPrevia() {
    const n = ++pedidoPrevia
    cargandoPrevia.value = true
    errorPrevia.value = ''
    try {
      const r = await vistaPrevia(aPayload(form))
      if (n === pedidoPrevia) previa.value = r
    } catch (e) {
      if (n === pedidoPrevia) {
        previa.value = null
        errorPrevia.value = mensaje(e)
      }
    } finally {
      if (n === pedidoPrevia) cargandoPrevia.value = false
    }
  }

  /** Crea las nuevas de la vista previa; el panel pasa a mostrar el resultado. */
  async function crear() {
    if (creando.value || errores.value.length) return
    creando.value = true
    // Una vista previa en vuelo ya no debe pisar nada.
    pedidoPrevia++
    cargandoPrevia.value = false
    try {
      const r = await programar(aPayload(form))
      resultado.value = r
      notify[r.creadas ? 'success' : 'info'](r.creadas === 1 ? 'Se creó 1 salida.' : `Se crearon ${r.creadas} salidas.`)
      if (r.esquema) {
        esquemas.value = [...esquemas.value.filter((e) => e.id !== r.esquema!.id), r.esquema].sort((a, b) => a.nombre.localeCompare(b.nombre))
        // El formulario ya es el esquema: solo se marca como cargado.
        esquemaId.value = r.esquema.id
        guardar.value = false
        nombreEsquema.value = ''
      }
    } catch (e) {
      notify.error(mensaje(e))
    } finally {
      creando.value = false
    }
  }

  /** Vuelve de un resultado a la vista previa (con lo recién creado como «ya existe»). */
  function seguirProgramando() {
    resultado.value = null
    previa.value = null
    if (!errores.value.length) void cargarPrevia()
  }

  /**
   * Enciende, apaga o ajusta el modo automático del esquema cargado (ADR-044).
   * Al encenderlo, la cadencia cuenta desde el primer día del formulario (o hoy).
   */
  async function automatizarEsquema(automatico: boolean, horizonteDias: number) {
    const actual = esquema.value
    if (!actual) return
    guardandoEsquema.value = true
    try {
      const ancla = automatico && !actual.automatico ? (form.desde ?? null) : actual.ancla
      const e = await guardarEsquema(actual.id, automatizacion(actual, automatico, horizonteDias, ancla))
      esquemas.value = esquemas.value.map((x) => (x.id === e.id ? e : x))
      notify.success(
        e.automatico
          ? `«${e.nombre}» se mantiene programado ${e.horizonteDias} días adelante; las salidas se crean en segundo plano.`
          : `«${e.nombre}» ya no se programa solo. Las salidas creadas siguen.`,
      )
    } catch (e) {
      notify.error(mensaje(e))
    } finally {
      guardandoEsquema.value = false
    }
  }

  async function actualizarEsquema() {
    const actual = esquema.value
    if (!actual) return
    guardandoEsquema.value = true
    try {
      const e = await guardarEsquema(actual.id, aEsquema(form, actual.nombre))
      esquemas.value = esquemas.value.map((x) => (x.id === e.id ? e : x))
      notify.success(`Esquema «${e.nombre}» actualizado.`)
    } catch (e) {
      notify.error(mensaje(e))
    } finally {
      guardandoEsquema.value = false
    }
  }

  /** @returns si se borró */
  async function borrarEsquema(): Promise<boolean> {
    const actual = esquema.value
    if (!actual) return false
    guardandoEsquema.value = true
    try {
      const { id } = await eliminarEsquema(actual.id)
      esquemaId.value = null
      esquemas.value = esquemas.value.filter((x) => x.id !== id)
      notify.success(`Esquema «${actual.nombre}» borrado.`)
      return true
    } catch (e) {
      notify.error(mensaje(e))
      return false
    } finally {
      guardandoEsquema.value = false
    }
  }

  /**
   * Al entrar a la pantalla: opciones y esquemas frescos, el día no puede
   * haber quedado en el pasado y un resultado de la visita anterior se
   * cambia por la vista previa. `esquemaPedido` (`?esquema=`) se carga.
   */
  async function iniciar(esquemaPedido: number | null) {
    const hoy = aDia(new Date())
    if (!form.desde || form.desde < hoy) form.desde = hoy
    if (resultado.value) seguirProgramando()
    try {
      const [o, e] = await Promise.all([fetchOpciones(), fetchEsquemas()])
      opciones.value = o
      esquemas.value = e.items
      sinPermiso.value = !o.puede.crear
      if (esquemaPedido && e.items.some((x) => x.id === esquemaPedido)) cargarEsquema(esquemaPedido)
    } catch (e) {
      if (e instanceof HttpError && e.status === 403) sinPermiso.value = true
      else notify.error(mensaje(e))
    }
  }

  return {
    opciones,
    sinPermiso,
    form,
    ruta,
    esquemas,
    esquemaId,
    esquema,
    cambiosEsquema,
    guardandoEsquema,
    guardar,
    nombreEsquema,
    previa,
    cargandoPrevia,
    errorPrevia,
    creando,
    resultado,
    dias,
    errores,
    pideEmpresa,
    empresas,
    clases,
    busesVisibles,
    croquisVisibles,
    busDe,
    elegirCroquis,
    elegirBus,
    agregarHora,
    quitarHora,
    ordenarHoras,
    cargarEsquema,
    cargarPrevia,
    crear,
    seguirProgramando,
    actualizarEsquema,
    automatizarEsquema,
    borrarEsquema,
    iniciar,
  }
})
