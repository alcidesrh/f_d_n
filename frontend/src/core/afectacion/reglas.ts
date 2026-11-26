/**
 * Afectaciones (ADR-070), reglas puras: etiquetas, qué lugares ofrece el
 * formulario de reportar para cada tipo y cómo se dice en qué va el
 * centinela. Quien decide (dónde cae, quién la atiende, cuándo avisa) es el
 * backend; esto solo muestra y propone.
 */
import type { Afectacion, BusReportable, EstadoAfectacion, Lugar, ParaReportar, PrioridadAfectacion, Reporte, SujetoAfectacion, TipoReportable } from './tipos'

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'

export const PRIORIDADES: Record<PrioridadAfectacion, { etiqueta: string; severidad: Severidad }> = {
  urgente: { etiqueta: 'Urgente', severidad: 'danger' },
  grave: { etiqueta: 'Grave', severidad: 'warn' },
  normal: { etiqueta: 'Normal', severidad: 'secondary' },
}

export const ESTADOS: Record<EstadoAfectacion, { etiqueta: string; severidad: Severidad; explicacion: string }> = {
  creada: { etiqueta: 'Creada', severidad: 'info', explicacion: 'Todavía no le llegó a nadie que la pueda atender.' },
  pendiente: { etiqueta: 'Pendiente', severidad: 'warn', explicacion: 'Ya se avisó a quien la atiende.' },
  resuelta: { etiqueta: 'Resuelta', severidad: 'success', explicacion: 'Quien la atendió la dio por resuelta.' },
}

export const SUJETOS: Record<SujetoAfectacion, string> = {
  estacion: 'Estación',
  bus: 'Bus',
  empresa: 'Empresa',
}

const dentro = (permitidos: number[] | null, id: number | null) => id !== null && (permitidos === null || permitidos.includes(id))

/** Las empresas donde el usuario puede reportar ese tipo. */
export function empresasPara(tipo: TipoReportable | null, datos: ParaReportar): Lugar[] {
  if (!tipo) return []
  return datos.empresas.filter((e) => dentro(tipo.empresas, e.id))
}

/** Las estaciones donde puede reportarlo (solo los tipos que caen en una estación). */
export function estacionesPara(tipo: TipoReportable | null, datos: ParaReportar): Lugar[] {
  if (!tipo || tipo.sujeto !== 'estacion') return []
  return datos.estaciones.filter((e) => dentro(tipo.estaciones, e.id))
}

/** Los buses de las empresas donde puede reportarlo (solo los tipos que caen en un bus). */
export function busesPara(tipo: TipoReportable | null, datos: ParaReportar): BusReportable[] {
  if (!tipo || tipo.sujeto !== 'bus') return []
  return datos.buses.filter((b) => tipo.empresas === null || dentro(tipo.empresas, b.empresaId))
}

/** Lo que el formulario propone al elegir un tipo: su estación de trabajo y la empresa, si hay una sola. */
export function propuesta(tipo: TipoReportable | null, datos: ParaReportar): { empresa: number | null; estacion: number | null } {
  const empresas = empresasPara(tipo, datos)
  const estaciones = estacionesPara(tipo, datos)
  const propia = datos.estacion && estaciones.some((e) => e.id === datos.estacion?.id) ? datos.estacion.id : null
  return {
    empresa: empresas.length === 1 ? empresas[0]!.id : null,
    estacion: propia ?? (estaciones.length === 1 ? estaciones[0]!.id : null),
  }
}

/** Lo que falta para poder enviar el reporte; null si está completo. */
export function faltaParaReportar(tipo: TipoReportable | null, r: Omit<Reporte, 'tipo'>, datos: ParaReportar): string | null {
  if (!tipo) return 'Elija qué pasó.'
  if (!r.descripcion.trim()) return 'Cuente qué pasó.'
  if (tipo.sujeto === 'bus' && !r.bus) return 'Elija el bus.'
  if (tipo.sujeto === 'estacion' && !r.estacion) return 'Elija la estación.'
  // Con el bus, la empresa es la del bus.
  if (tipo.sujeto !== 'bus' && !r.empresa && empresasPara(tipo, datos).length !== 1) return 'Elija la empresa.'
  return null
}

/** El cuerpo del POST: solo lo que el tipo usa. */
export function reporteDe(tipo: TipoReportable, r: Omit<Reporte, 'tipo'>): Reporte {
  return {
    tipo: tipo.id,
    descripcion: r.descripcion.trim(),
    empresa: tipo.sujeto === 'bus' ? null : (r.empresa ?? null),
    estacion: tipo.sujeto === 'empresa' ? null : (r.estacion ?? null),
    bus: tipo.sujeto === 'bus' ? (r.bus ?? null) : null,
  }
}

/** `45 min`, `3 h`, `2 días` (como los avisos del backend, `ReglasAfectacion::lapso`). */
export function lapso(minutos: number): string {
  const m = Math.max(0, Math.floor(minutos))
  if (m < 60) return `${m} min`
  if (m < 48 * 60) return `${Math.floor(m / 60)} h`
  return `${Math.floor(m / (24 * 60))} días`
}

const minutosEntre = (desde: string, hasta: Date) => (hasta.getTime() - new Date(desde).getTime()) / 60000

/** En qué va el centinela con esta afectación, en una línea. */
export function enQueVa(a: Afectacion, ahora: Date): string {
  if (a.estado === 'resuelta') {
    const quien = a.resueltaPor ? ` por ${a.resueltaPor.nombre}` : ''
    return a.resueltaEn ? `Resuelta${quien} en ${lapso(minutosEntre(a.creadaEn, new Date(a.resueltaEn)))}.` : `Resuelta${quien}.`
  }
  const abierta = `Abierta hace ${lapso(minutosEntre(a.creadaEn, ahora))}`
  const avisos = a.avisos === 0 ? 'sin avisos entregados' : a.avisos === 1 ? '1 aviso' : `${a.avisos} avisos`
  const escalada = a.escaladaEn ? ', escalada' : ''
  const proximo = a.proximoAviso ? proximoTexto(a.proximoAviso, ahora) : ''
  return `${abierta} · ${avisos}${escalada}${proximo}.`
}

function proximoTexto(iso: string, ahora: Date): string {
  const faltan = -minutosEntre(iso, ahora)
  return faltan < 1 ? ' · avisa en el próximo minuto' : ` · próximo aviso en ${lapso(faltan)}`
}
