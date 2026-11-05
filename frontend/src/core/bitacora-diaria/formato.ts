/**
 * La línea de tiempo de la bitácora diaria dicha para quien la lee: título y
 * detalle de cada entrada, y las entradas agrupadas por petición
 * (`requestId`: la petición y los cambios de negocio que hizo).
 */
import { lineasDeDetalle } from '@/core/bitacora/formato'
import type { TipoBitacora } from '@/core/bitacora/types'
import type { EntradaActividad, EntradaDiaria, EntradaNegocio } from './types'

const TIPOS_BITACORA: readonly string[] = ['salida', 'boleto', 'encomienda', 'usuario', 'rol', 'autorizacion']

const SESION: Record<string, string> = { inicio: 'Inició sesión', cierre: 'Cerró sesión', fallo: 'Intento de inicio fallido' }

const OPERACIONES_CAMBIO: Record<string, string> = { creado: 'Creó', modificado: 'Modificó', eliminado: 'Eliminó' }

const METODOS: Record<string, string> = { GET: 'Consultó', POST: 'Creó', PUT: 'Guardó', PATCH: 'Modificó', DELETE: 'Eliminó' }

const texto = (v: unknown): string | null => (v === null || v === undefined || v === '' ? null : String(v))

/** `1 h 25 min`, `40 min`, `0 min`. */
export function duracion(minutos: number): string {
  const h = Math.floor(minutos / 60)
  const m = Math.round(minutos % 60)
  return h > 0 ? `${h} h${m ? ` ${m} min` : ''}` : `${m} min`
}

/** `2 s`, `850 ms`, `3 min 10 s`. */
export function milisegundos(ms: number): string {
  if (ms < 1000) return `${ms} ms`
  const s = Math.round(ms / 1000)
  return s < 60 ? `${s} s` : `${Math.floor(s / 60)} min${s % 60 ? ` ${s % 60} s` : ''}`
}

/** Hora local `HH:MM:SS` de una fecha ISO (la hora de Guatemala ya viene en el desfase). */
export function hora(iso: string): string {
  const m = /T(\d{2}:\d{2}:\d{2})/.exec(iso)
  return m ? m[1]! : iso
}

function tituloActividad(e: EntradaActividad): string {
  switch (e.tipo) {
    case 'sesion':
      return SESION[e.accion ?? ''] ?? `Sesión: ${e.accion ?? ''}`
    case 'sondeo':
      return `Consultas automáticas (${texto(e.detalle?.cantidad) ?? '?'})`
    case 'pantalla':
      if (e.accion === 'navegar') return `Abrió ${texto(e.detalle?.titulo) ?? e.ruta ?? 'una pantalla'}`
      if (e.accion === 'imprimir') return `Imprimió ${texto(e.detalle?.titulo) ?? 'la pantalla'}`
      return `En pantalla: ${e.accion ?? ''}`
    case 'peticion': {
      if (e.accion) {
        const [tipo, nombre] = e.accion.split(' ', 2)
        return tipo === 'mutation' ? `Ejecutó ${nombre}` : `Consultó ${nombre ?? e.accion}`
      }
      const verbo = METODOS[e.metodo ?? ''] ?? e.metodo ?? ''
      return `${verbo} ${e.entidad ?? e.ruta ?? ''}${e.registroId ? ` #${e.registroId}` : ''}`.trim()
    }
  }
}

function tituloNegocio(e: EntradaNegocio): string {
  if (e.entidad === 'cambio') {
    const d = e.detalle ?? {}
    const que = texto(d.etiqueta) ?? `${texto(d.clase) ?? 'registro'}${d.id !== undefined ? ` #${String(d.id)}` : ''}`
    return `${OPERACIONES_CAMBIO[e.accion ?? ''] ?? e.etiqueta} ${texto(d.clase) ?? ''}: ${que}`.replace(/\s+:/, ':')
  }
  return `${e.etiqueta}${e.registroId ? ` (${e.entidad} #${e.registroId})` : ''}`
}

/** Una línea que dice qué pasó. */
export function titulo(e: EntradaDiaria): string {
  return e.origen === 'negocio' ? tituloNegocio(e) : tituloActividad(e)
}

/** Líneas de detalle de la entrada (sin vacías). */
export function lineas(e: EntradaDiaria): string[] {
  const d = e.detalle ?? {}
  if (e.origen === 'negocio') {
    if (e.entidad === 'cambio') {
      const cambios = (d.cambios ?? {}) as Record<string, { de?: unknown; a?: unknown }>
      return Object.entries(cambios).map(([campo, c]) => `${campo}: ${texto(c?.de) ?? '—'} → ${texto(c?.a) ?? '—'}`)
    }
    return e.entidad && TIPOS_BITACORA.includes(e.entidad) ? lineasDeDetalle(e.entidad as TipoBitacora, { operacion: e.accion ?? '', detalle: e.detalle }) : []
  }
  const salida: Array<string | null> = []
  if (e.tipo === 'peticion') {
    salida.push(`${e.metodo ?? ''} ${e.ruta ?? ''}`.trim() || null)
    if (d.consulta && typeof d.consulta === 'object') salida.push(`Filtros: ${resumir(d.consulta)}`)
    if (d.cuerpo && typeof d.cuerpo === 'object') salida.push(`Datos: ${resumir(d.cuerpo)}`)
  } else if (e.tipo === 'pantalla' && e.accion === 'navegar' && e.duracionMs) {
    salida.push(`Estuvo ${milisegundos(e.duracionMs)} en ${texto(d.desde) ?? 'la pantalla anterior'}`)
  } else if (e.tipo === 'sondeo') {
    salida.push(e.ruta)
  } else if (e.tipo === 'sesion' && e.accion === 'fallo') {
    salida.push(texto(d.usuario) ? `Usuario: ${texto(d.usuario)}` : null, texto(d.motivo))
  }
  if (d.recortado) salida.push(`Detalle recortado (${texto(d.bytes) ?? '?'} bytes)`)
  return salida.filter((l): l is string => l !== null && l !== '')
}

/** `clave=valor, …` de un objeto de detalle, acotado. */
export function resumir(datos: unknown, maximo = 160): string {
  const plano = Object.entries((datos ?? {}) as Record<string, unknown>)
    .map(([k, v]) => `${k}=${v !== null && typeof v === 'object' ? JSON.stringify(v) : String(v)}`)
    .join(', ')
  return plano.length > maximo ? `${plano.slice(0, maximo - 1)}…` : plano
}

/** La entrada fue un error (estado ≥ 400 o intento de sesión fallido). */
export const esError = (e: EntradaDiaria) => e.origen === 'actividad' && ((e.estado ?? 0) >= 400 || (e.tipo === 'sesion' && e.accion === 'fallo'))

export interface GrupoDeEntradas {
  clave: string
  /** La entrada principal (la petición, o la primera si no hay). */
  principal: EntradaDiaria
  /** Lo que hizo esa misma petición en la bitácora de negocio. */
  efectos: EntradaNegocio[]
}

/**
 * Junta cada cambio de negocio con la petición que lo hizo (mismo
 * `requestId`). Mantiene el orden de las entradas; un cambio sin petición
 * anotada queda solo.
 */
export function agrupar(entradas: EntradaDiaria[]): GrupoDeEntradas[] {
  const grupos: GrupoDeEntradas[] = []
  const porPeticion = new Map<string, GrupoDeEntradas>()
  entradas.forEach((e, i) => {
    const previo = e.requestId ? porPeticion.get(e.requestId) : undefined
    if (previo && e.origen === 'negocio') {
      previo.efectos.push(e)
      return
    }
    const grupo: GrupoDeEntradas = { clave: `${e.requestId ?? 'sin'}-${i}`, principal: e, efectos: [] }
    grupos.push(grupo)
    if (e.requestId && !porPeticion.has(e.requestId)) porPeticion.set(e.requestId, grupo)
  })
  return grupos
}

/** `AAAA-MM-DD` de una fecha en la hora local del navegador. */
export function diaIso(fecha: Date): string {
  const dos = (n: number) => String(n).padStart(2, '0')
  return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}`
}

/** La fecha `AAAA-MM-DD` como `Date` local (para el selector), o null si no es válida. */
export function desdeDiaIso(dia: string | null | undefined): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dia ?? '')
  return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null
}
