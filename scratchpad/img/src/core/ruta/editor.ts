/**
 * Formulario del editor de rutas (puro, ADR-030): paradas en orden, mover,
 * agregar y quitar, errores que impiden guardar y avisos que no (espejo de
 * `App\Ruta\Paradas`).
 */
import type { ParadaRuta, RutaDetalle, RutaPayload, TiempoClase } from './types'

export interface ParadaForm {
  /** Estable mientras se edita (para la lista reordenable). */
  key: string
  enclaveId: number | null
  minutos: number | null
  km: number | null
  permiteSubir: boolean
  permiteBajar: boolean
  tiempos: TiempoClase[]
}

export interface RutaForm {
  nombre: string
  kilometros: number | null
  activo: boolean
  paradas: ParadaForm[]
}

let siguiente = 0
const clave = () => `p${++siguiente}`

export function nuevaParada(enclaveId: number | null = null): ParadaForm {
  return { key: clave(), enclaveId, minutos: null, km: null, permiteSubir: true, permiteBajar: true, tiempos: [] }
}

export function formVacio(): RutaForm {
  return { nombre: '', kilometros: null, activo: true, paradas: [nuevaParada(), nuevaParada()] }
}

export function formDe(r: RutaDetalle): RutaForm {
  return {
    nombre: r.nombre,
    kilometros: r.kilometros,
    activo: r.activo,
    paradas: r.paradas.map((p: ParadaRuta) => ({ ...p, key: clave(), tiempos: p.tiempos.map((t) => ({ ...t })) })),
  }
}

/** La lista con la parada `i` movida `delta` posiciones (sin salirse). */
export function mover<T>(lista: T[], i: number, delta: number): T[] {
  const j = Math.max(0, Math.min(lista.length - 1, i + delta))
  if (i === j) return lista
  const copia = [...lista]
  const [p] = copia.splice(i, 1)
  copia.splice(j, 0, p!)
  return copia
}

/** Lo que impide guardar. */
export function errores(f: RutaForm): string[] {
  const e: string[] = []
  if (!f.nombre.trim()) e.push('Escriba el nombre de la ruta.')
  if (f.paradas.length < 2) e.push('Una ruta necesita al menos dos paradas: el origen y el destino.')
  f.paradas.forEach((p, i) => {
    if (!p.enclaveId) e.push(`Elija el lugar de la parada ${i + 1}.`)
  })
  const vistos = new Map<number, number>()
  f.paradas.forEach((p, i) => {
    if (!p.enclaveId) return
    const antes = vistos.get(p.enclaveId)
    if (antes !== undefined) e.push(`Un lugar aparece dos veces (paradas ${antes + 1} y ${i + 1}).`)
    else vistos.set(p.enclaveId, i)
  })
  return e
}

/** Paradas cuyos minutos son menos que los de una anterior (se puede guardar igual). */
export function avisos(paradas: Array<Pick<ParadaForm, 'minutos'>>): Record<number, string> {
  const r: Record<number, string> = {}
  let anterior = 0
  paradas.forEach((p, i) => {
    if (i === 0 || p.minutos === null) return
    if (p.minutos < anterior) r[i] = `${p.minutos} min es menos que una parada anterior (${anterior} min): revise el orden o los minutos.`
    else anterior = p.minutos
  })
  return r
}

export function payload(f: RutaForm): RutaPayload {
  return {
    nombre: f.nombre.trim(),
    kilometros: f.kilometros,
    activo: f.activo,
    paradas: f.paradas.map((p, i) => ({
      enclaveId: p.enclaveId as number,
      minutos: i === 0 ? null : p.minutos,
      km: p.km,
      permiteSubir: p.permiteSubir,
      permiteBajar: p.permiteBajar,
      tiempos: i === 0 ? [] : p.tiempos.filter((t) => t.claseId && Number.isFinite(t.minutos)),
    })),
  }
}

/** `4 h 50 min` */
export function duracion(minutos: number | null): string {
  if (minutos === null) return '—'
  const h = Math.floor(minutos / 60)
  const m = minutos % 60
  return h ? `${h} h${m ? ` ${m} min` : ''}` : `${m} min`
}
