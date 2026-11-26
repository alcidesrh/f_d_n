/**
 * Inventario (ADR-071), reglas puras: etiquetas, cómo se escriben las
 * cantidades y el dinero, qué puede registrar cada uno en un almacén y qué
 * le falta al formulario. Quien decide (permisos, existencia suficiente,
 * costo promedio) es el backend; esto solo muestra y avisa antes.
 */
import type { Borrador, EstadoExistencia, Niveles, PuedeEnAlmacen, Registro, TipoAlmacen, TipoRegistrable } from './tipos'

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'

export const ESTADOS: Record<EstadoExistencia, { etiqueta: string; severidad: Severidad }> = {
  agotado: { etiqueta: 'Agotado', severidad: 'danger' },
  bajo_minimo: { etiqueta: 'Bajo el mínimo', severidad: 'warn' },
  por_reponer: { etiqueta: 'Por reponer', severidad: 'info' },
  bien: { etiqueta: 'Bien', severidad: 'success' },
}

export const TIPOS_ALMACEN: Record<TipoAlmacen, string> = {
  estacion: 'Estación',
  taller: 'Taller',
  oficina: 'Oficina',
  bus: 'Bus',
}

export const REGISTRABLES: Record<TipoRegistrable, { etiqueta: string; ayuda: string }> = {
  entrada: { etiqueta: 'Entrada', ayuda: 'Lo que llegó comprado, con su factura y su costo.' },
  salida: { etiqueta: 'Salida', ayuda: 'Lo que se gastó o se instaló.' },
  envio: { etiqueta: 'Envío', ayuda: 'Sale hacia otro almacén; entra allá cuando lo reciben.' },
  ajuste: { etiqueta: 'Conteo', ayuda: 'Lo que hay de verdad: se anota la diferencia con lo que decía el sistema.' },
}

/** Lo que puede registrar en el almacén: entradas, salidas y envíos con `mover`, conteos con `ajustar`. Uno inactivo solo se cuenta. */
export function tiposPermitidos(puede: PuedeEnAlmacen, activo = true): TipoRegistrable[] {
  const tipos: TipoRegistrable[] = puede.mover && activo ? ['entrada', 'salida', 'envio'] : []
  return puede.ajustar ? [...tipos, 'ajuste'] : tipos
}

const NUMERO = (decimales: number) => new RegExp(`^\\d{1,12}(\\.\\d{0,${decimales}})?$`)

/** ¿Es una cantidad que acepta el backend? Hasta 2 decimales, sin signo. */
export function cantidadValida(texto: string, decimales = 2): boolean {
  return NUMERO(decimales).test(texto.trim().replace(/,/g, ''))
}

/** `"12.50"` → `"12.5"`, `"3.00"` → `"3"`, con separador de miles. */
export function cantidad(texto: string | null | undefined): string {
  if (texto === null || texto === undefined || texto === '') return '—'
  const n = Number(texto)
  if (!Number.isFinite(n)) return texto
  return n.toLocaleString('es-GT', { maximumFractionDigits: 2 })
}

/** `"1234.5"` → `"Q1,234.50"`; los costos unitarios con hasta 4 decimales. */
export function quetzales(texto: string | null | undefined, unitario = false): string {
  const n = Number(texto ?? 0)
  return `Q${(Number.isFinite(n) ? n : 0).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: unitario ? 4 : 2 })}`
}

/** La cantidad con signo del libro: `"-5.00"` → `"−5"`, `"5.00"` → `"+5"`. */
export function conSigno(texto: string): string {
  const n = Number(texto)
  if (!Number.isFinite(n) || n === 0) return cantidad(texto)
  return `${n > 0 ? '+' : '−'}${cantidad(String(Math.abs(n)))}`
}

/** «Estación Central · Transportes del Norte» */
export function nombreAlmacen(a: { nombre: string; empresa: { nombre: string } | null }, conEmpresa = false): string {
  return conEmpresa && a.empresa ? `${a.nombre} · ${a.empresa.nombre}` : a.nombre
}

export function borradorVacio(tipo: TipoRegistrable, articulo: number | null = null): Borrador {
  return { tipo, articulo, cantidad: '', costo: '', destino: null, documento: '', motivo: '' }
}

/** Lo que falta para registrar; null si está completo. */
export function faltaParaRegistrar(b: Borrador): string | null {
  if (!b.articulo) return 'Elija el artículo.'
  if (!cantidadValida(b.cantidad)) return b.tipo === 'ajuste' ? 'Escriba lo contado (hasta 2 decimales).' : 'Escriba la cantidad (hasta 2 decimales).'
  if (b.tipo !== 'ajuste' && Number(b.cantidad.replace(/,/g, '')) <= 0) return 'La cantidad debe ser mayor que cero.'
  if (b.tipo === 'entrada' && !cantidadValida(b.costo, 4)) return 'Escriba el costo por unidad (hasta 4 decimales).'
  if (b.tipo === 'envio' && !b.destino) return 'Elija a qué almacén se envía.'
  if (b.tipo === 'ajuste' && !b.motivo.trim()) return 'Diga por qué se ajusta (conteo, merma, rotura…).'
  return null
}

/** El cuerpo de `POST /movimientos`, sin lo que no aplica al tipo. */
export function registroDe(almacen: number, b: Borrador): Registro {
  const limpio = (t: string) => t.trim().replace(/,/g, '')
  const r: Registro = { tipo: b.tipo, almacen, articulo: b.articulo ?? 0, cantidad: limpio(b.cantidad) }
  if (b.tipo === 'entrada') r.costo = limpio(b.costo)
  if (b.tipo === 'envio' && b.destino) r.destino = b.destino
  if (b.documento.trim()) r.documento = b.documento.trim()
  if (b.motivo.trim()) r.motivo = b.motivo.trim()
  return r
}

/** Lo que dice el backend de los niveles, antes de mandarlos; null si van bien. */
export function faltaEnNiveles(n: Niveles): string | null {
  const valor = (t: string) => (t.trim() === '' ? null : t.trim().replace(/,/g, ''))
  const [minimo, reorden, maximo] = [valor(n.minimo), valor(n.puntoReorden), valor(n.maximo)]
  for (const v of [minimo, reorden, maximo]) if (v !== null && !cantidadValida(v)) return 'Cada nivel es un número con hasta 2 decimales.'
  const min = Number(minimo ?? 0)
  if (reorden !== null && Number(reorden) < min) return 'El punto de reorden va en el mínimo o por encima.'
  if (maximo !== null && Number(maximo) < Math.max(min, Number(reorden ?? 0))) return 'El máximo va por encima del mínimo y del punto de reorden.'
  return null
}

/** Para editar: lo que tiene la existencia, sin ceros de más. */
export function nivelesDe(e: { minimo: string; puntoReorden: string | null; maximo: string | null } | null): Niveles {
  const t = (v: string | null | undefined) => (v === null || v === undefined || Number(v) === 0 ? '' : String(Number(v)))
  return { minimo: t(e?.minimo), puntoReorden: t(e?.puntoReorden), maximo: t(e?.maximo) }
}

/** «26 nov. 2026, 10:05» en la hora del navegador. */
export function fecha(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-GT', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
