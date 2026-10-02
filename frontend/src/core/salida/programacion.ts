/**
 * Programador de salidas (ADR-024): estado del formulario → payload de
 * `/api/gestion-salidas/programacion` (espejo de `App\Salida\Programacion`),
 * validación previa, días que se programan y conversión desde/hacia un
 * esquema guardado. Funciones puras.
 */
import { aDia, deDia } from './filtro'
import type { Esquema, EsquemaPayload, Momento, ProgramacionPayload } from './types'

/** Topes del backend (`Programacion::MAX_*`). */
export const MAX_MOMENTOS = 48
export const MAX_INTERVALO = 60
export const MAX_DIAS = 366
export const MAX_SALIDAS = 3000

/** `no`: solo el primer día; `hasta`: hasta una fecha; `veces`: N días en total. */
export type Repeticion = 'no' | 'hasta' | 'veces'

export interface ProgramadorForm {
  trayectoId: number | null
  momentos: Momento[]
  /** `AAAA-MM-DD` */
  desde: string | null
  repeticion: Repeticion
  /** 1 = días consecutivos, 2 = día por medio, … */
  intervaloDias: number
  hasta: string | null
  veces: number
  /** Guardar la configuración como esquema nuevo con este nombre. */
  guardarComo: string | null
}

export function formInicial(hoy = new Date()): ProgramadorForm {
  return {
    trayectoId: null,
    momentos: [{ hora: '06:00', busId: null }],
    desde: aDia(hoy),
    repeticion: 'no',
    intervaloDias: 1,
    hasta: null,
    veces: 7,
    guardarComo: null,
  }
}

export const horaValida = (h: string) => /^([01]?\d|2[0-3]):[0-5]\d$/.test(h.trim())

/** `H:MM` → `HH:MM`. */
export const normalizarHora = (h: string) => {
  const [hh, mm] = h.trim().split(':')
  return `${String(Number(hh)).padStart(2, '0')}:${mm}`
}

export function sumarDias(dia: string, n: number): string {
  const d = deDia(dia)
  d.setDate(d.getDate() + n)
  return aDia(d)
}

/** Último día de la repetición (inclusive), o null si es un solo día. */
export function fechaHasta(f: ProgramadorForm): string | null {
  if (!f.desde || f.repeticion === 'no') return null
  if (f.repeticion === 'veces') return sumarDias(f.desde, (Math.max(1, Math.floor(f.veces)) - 1) * f.intervaloDias)
  return f.hasta
}

/** Días (`AAAA-MM-DD`) en que hay salidas. Vacío si el rango no es válido. */
export function diasProgramados(f: ProgramadorForm): string[] {
  if (!f.desde) return []
  const hasta = fechaHasta(f) ?? f.desde
  if (hasta < f.desde || f.intervaloDias < 1) return []
  const dias: string[] = []
  for (let d = f.desde; d <= hasta && dias.length <= MAX_DIAS; d = sumarDias(d, f.intervaloDias)) dias.push(d)
  return dias
}

export const totalSalidas = (f: ProgramadorForm) => diasProgramados(f).length * f.momentos.length

/** Problemas que impiden programar, en el orden del formulario. Vacío = se puede pedir la vista previa. */
export function errores(f: ProgramadorForm, hoy = new Date()): string[] {
  const e: string[] = []
  if (!f.trayectoId) e.push('Elija el trayecto.')
  if (!f.momentos.length) e.push('Agregue al menos una hora de salida.')
  if (f.momentos.length > MAX_MOMENTOS) e.push(`No más de ${MAX_MOMENTOS} horas por día.`)
  const vistos = new Set<string>()
  f.momentos.forEach((m, i) => {
    if (!horaValida(m.hora)) e.push(`La hora n.º ${i + 1} no es válida (HH:MM).`)
    if (!m.busId) e.push(`Elija el bus de la hora n.º ${i + 1}.`)
    const clave = `${horaValida(m.hora) ? normalizarHora(m.hora) : m.hora}|${m.busId}`
    if (m.busId && vistos.has(clave)) e.push(`El mismo bus está dos veces a las ${m.hora}.`)
    vistos.add(clave)
  })
  if (!f.desde) e.push('Elija el día.')
  else if (f.desde < aDia(hoy)) e.push('El día ya pasó.')
  if (f.repeticion !== 'no') {
    if (f.intervaloDias < 1 || f.intervaloDias > MAX_INTERVALO) e.push(`El intervalo debe estar entre 1 y ${MAX_INTERVALO} días.`)
    const hasta = fechaHasta(f)
    if (f.repeticion === 'hasta' && !hasta) e.push('Elija hasta qué fecha se repite.')
    if (f.repeticion === 'veces' && f.veces < 1) e.push('Indique cuántos días.')
    if (f.desde && hasta && hasta < f.desde) e.push('La fecha final es anterior al primer día.')
    if (f.desde && hasta && (deDia(hasta).getTime() - deDia(f.desde).getTime()) / 86_400_000 >= MAX_DIAS) e.push('Se puede programar como máximo un año de una vez.')
  }
  if (f.guardarComo !== null && !f.guardarComo.trim()) e.push('Escriba el nombre del esquema a guardar.')
  const total = totalSalidas(f)
  if (total > MAX_SALIDAS) e.push(`Serían ${total} salidas; el máximo de una vez es ${MAX_SALIDAS}.`)
  return e
}

const momentosPayload = (f: ProgramadorForm) =>
  f.momentos.filter((m) => m.busId).map((m) => ({ hora: normalizarHora(m.hora), busId: m.busId as number }))

/** Payload de la vista previa / creación (llamar solo sin `errores`). */
export function aPayload(f: ProgramadorForm): ProgramacionPayload {
  const p: ProgramacionPayload = {
    trayectoId: f.trayectoId as number,
    momentos: momentosPayload(f),
    desde: f.desde as string,
    hasta: fechaHasta(f),
    intervaloDias: f.repeticion === 'no' ? 1 : f.intervaloDias,
  }
  if (f.guardarComo?.trim()) p.guardarComo = f.guardarComo.trim()
  return p
}

/** Carga un esquema en el formulario: trayecto, horas con su bus e intervalo; conserva los días elegidos. */
export function conEsquema(f: ProgramadorForm, e: Esquema): ProgramadorForm {
  return {
    ...f,
    trayectoId: e.trayecto.id,
    momentos: e.momentos.map((m) => ({ hora: m.hora, busId: m.busId })),
    intervaloDias: e.intervaloDias,
    repeticion: f.repeticion === 'no' && e.intervaloDias > 1 ? 'veces' : f.repeticion,
    guardarComo: null,
  }
}

export function aEsquema(f: ProgramadorForm, nombre: string): EsquemaPayload {
  return { nombre: nombre.trim(), trayectoId: f.trayectoId as number, intervaloDias: f.intervaloDias, momentos: momentosPayload(f) }
}

/** ¿El formulario tiene cambios respecto del esquema cargado (trayecto, intervalo u horas/buses)? */
export function difiereDeEsquema(f: ProgramadorForm, e: Esquema): boolean {
  const firma = (ms: Array<{ hora: string; busId: number | null }>) =>
    ms.map((m) => `${horaValida(m.hora) ? normalizarHora(m.hora) : m.hora}|${m.busId}`).sort().join(',')
  return f.trayectoId !== e.trayecto.id || f.intervaloDias !== e.intervaloDias || firma(f.momentos) !== firma(e.momentos)
}
