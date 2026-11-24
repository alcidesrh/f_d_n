import { describe, expect, it } from 'vitest'
import { aQuery, alternarOrden, consultaSalidas, disponibilidad, documentoTexto, filtroInicial, filtrosActivos, paginaInicial, porCercania } from '../listado'
import type { BoletoFila, PuedeBoleto } from '../types'

const TODO: PuedeBoleto = { cancelar: true, anular: true, reasignar: true, chequear: true }
/** El backend admite todas (`FlujoBoleto::acciones`). */
const LIBRES: BoletoFila['acciones'] = { chequear: null, cancelar: null, revertir: null, anular: null, reasignar: null }

function boleto(cambios: Partial<BoletoFila> = {}): BoletoFila {
  return {
    id: 10,
    estado: 'emitido',
    fecha: '2026-10-11T18:00:00-06:00',
    salidaId: 5,
    salidaEstado: 'programada',
    empresa: 'FDN',
    asiento: 3,
    clase: 'A',
    documento: { tipo: 'factura', serie: 'ABC', numero: 123 },
    ruta: 'RUT001 - Guatemala - Flores',
    sube: 'Guatemala',
    baja: 'Flores',
    clienteDocumento: null,
    clienteBoleto: null,
    tipoPago: 'Efectivo',
    precio: null,
    sinCobro: false,
    canal: 'estacion',
    ventaId: 9,
    ventaConfirmada: true,
    reasignadoDe: null,
    referenciaExterna: null,
    autorizacion: null,
    creada: null,
    usuario: null,
    estacion: null,
    acciones: LIBRES,
    ...cambios,
  }
}

describe('listado de boletos', () => {
  it('arma la query sin los filtros vacíos', () => {
    const f = { ...filtroInicial('2026-10-01', '2026-10-31'), id: 7, estados: ['emitido', 'chequeado'] as const, serie: '  ', origen: 7, destino: 9 }
    expect(aQuery({ ...f, estados: [...f.estados] }, paginaInicial())).toBe('desde=2026-10-01&hasta=2026-10-31&id=7&estado=emitido%2Cchequeado&origen=7&destino=9&pagina=1&porPagina=15&orden=id&dir=desc')
  })

  it('manda la empresa y solo los boletos propios cuando se eligen', () => {
    expect(aQuery({ ...filtroInicial(), empresa: 3, mios: true }, paginaInicial())).toContain('empresa=3&mios=1&')
    expect(aQuery({ ...filtroInicial(), mios: false }, paginaInicial())).not.toContain('mios')
  })

  it('manda el asiento y los canales cuando se eligen', () => {
    const q = aQuery({ ...filtroInicial(), asiento: 12, canales: ['estacion', 'web'] }, paginaInicial())
    expect(q).toContain('asiento=12&')
    expect(q).toContain('canal=estacion%2Cweb&')
    expect(aQuery(filtroInicial(), paginaInicial())).not.toMatch(/asiento|canal/)
  })

  it('cuenta los filtros aplicados sin el rango', () => {
    expect(filtrosActivos(filtroInicial('2026-10-01', '2026-10-31'))).toBe(0)
    expect(filtrosActivos({ ...filtroInicial(), estados: ['anulado'], asiento: 0, origen: 4 })).toBe(3)
    expect(filtrosActivos({ ...filtroInicial(), mios: true, empresa: 2 })).toBe(2)
    expect(filtrosActivos({ ...filtroInicial(), asiento: 3, canales: ['agencia', 'web'] })).toBe(2)
  })

  it('alterna el orden de una columna y vuelve a la primera página', () => {
    const p = { ...paginaInicial(), pagina: 4 }
    expect(alternarOrden(p, 'id')).toEqual({ pagina: 1, porPagina: 15, orden: 'id', dir: 'asc' })
    expect(alternarOrden({ ...p, dir: 'asc' }, 'id').dir).toBe('desc')
    expect(alternarOrden(p, 'fecha')).toMatchObject({ orden: 'fecha', dir: 'desc' })
  })

  it('describe el documento como el legado', () => {
    expect(documentoTexto({ tipo: 'factura', serie: 'FELSB', numero: 584248 })).toBe('Factura - FELSB 584248')
    expect(documentoTexto({ tipo: 'voucher', serie: null, numero: null })).toBe('Voucher')
    expect(documentoTexto({ tipo: 'pendiente', serie: null, numero: null })).toBe('Factura pendiente')
  })
})

describe('acciones del panel', () => {
  it('sin boleto elegido no hay nada que hacer y sin permiso la acción no se muestra', () => {
    const a = disponibilidad(null, { ...TODO, cancelar: false })
    expect(a.consultar).toEqual({ visible: true, habilitada: false, motivo: 'Elija un boleto de la lista' })
    expect(a.cancelar.visible).toBe(false)
    expect(a.revertir.visible).toBe(false)
    expect(a.anular.visible).toBe(true)
  })

  it('lo que cambia el estado lo decide el backend, con su motivo', () => {
    const motivo = 'La salida está programada: los boletos se chequean cuando la salida está abordando o iniciada.'
    const a = disponibilidad(boleto({ acciones: { ...LIBRES, chequear: motivo } }), TODO)
    expect(a.cancelar).toEqual({ visible: true, habilitada: true, motivo: null })
    expect(a.anular.habilitada).toBe(true)
    expect(a.reasignar.habilitada).toBe(true)
    expect(a.chequear).toEqual({ visible: true, habilitada: false, motivo })
    expect(a.correo.habilitada).toBe(true)
  })

  it('sin permiso no se muestra aunque el boleto la admita', () => {
    const a = disponibilidad(boleto(), { ...TODO, cancelar: false })
    expect(a.cancelar).toMatchObject({ visible: false, habilitada: false })
    expect(a.reasignar.visible).toBe(true)
  })

  it('anular y reasignar sin permiso o fuera de hora se ofrecen con autorización (ADR-040)', () => {
    const sinPermiso = disponibilidad(boleto(), { ...TODO, anular: false })
    expect(sinPermiso.anular).toMatchObject({ visible: true, habilitada: true, conAutorizacion: true })

    const motivo = 'La salida de las 07:56 ya empezó: solo se puede anular el boleto antes de la hora de salida.'
    const tarde = disponibilidad(boleto({ fueraDeHora: true, acciones: { ...LIBRES, anular: motivo, reasignar: motivo } }), TODO)
    expect(tarde.anular).toMatchObject({ habilitada: true, conAutorizacion: true })
    expect(tarde.anular.motivo).toContain('Requiere una autorización')

    // Chequeado, en tránsito, finalizado o con la salida finalizada: fuera de estado (ADR-058).
    const porEstado = 'El boleto 7 (asiento 12) está finalizado: solo se opera sobre boletos emitidos o pospuestos.'
    const finalizado = disponibilidad(boleto({ estado: 'finalizado', fueraDeEstado: true, acciones: { ...LIBRES, anular: porEstado, reasignar: porEstado } }), TODO)
    expect(finalizado.anular).toMatchObject({ habilitada: true, conAutorizacion: true, motivo: `${porEstado} · Requiere una autorización.` })
    expect(finalizado.reasignar).toMatchObject({ habilitada: true, conAutorizacion: true })

    // Lo que ni con autorización se puede (un anulado) sigue deshabilitado, con su motivo.
    const anulado = disponibilidad(boleto({ estado: 'anulado', acciones: { ...LIBRES, anular: 'El boleto está anulado' } }), { ...TODO, anular: false })
    expect(anulado.anular).toEqual({ visible: true, habilitada: false, motivo: 'El boleto está anulado' })
  })

  it('un anulado no se reenvía, reimprime ni descarga en PDF; una venta en proceso tampoco', () => {
    const anulado = disponibilidad(boleto({ estado: 'anulado' }), TODO)
    expect(anulado.correo).toMatchObject({ habilitada: false, motivo: 'El boleto está anulado' })
    expect(anulado.pdf).toMatchObject({ habilitada: false, motivo: 'El boleto está anulado' })
    expect(anulado.consultar.habilitada).toBe(true)

    const enProceso = disponibilidad(boleto({ ventaConfirmada: false }), TODO)
    expect(enProceso.factura.motivo).toBe('La venta todavía se está procesando')
    expect(enProceso.pdf.motivo).toBe('La venta todavía se está procesando')
  })

  it('el PDF se descarga con el permiso de ver: no pide permiso de operar, y sin boleto elegido no se ofrece', () => {
    const a = disponibilidad(boleto(), { ...TODO, anular: false, cancelar: false, reasignar: false, chequear: false })
    expect(a.pdf).toEqual({ visible: true, habilitada: true, motivo: null })

    const sinElegir = disponibilidad(null, TODO)
    expect(sinElegir.pdf).toMatchObject({
      habilitada: false,
      motivo: 'Elija un boleto de la lista',
    })
  })
})

describe('consultaSalidas', () => {
  const rango = { desde: '2026-10-25', hasta: '2026-12-24' }
  const de = (q: string) => Object.fromEntries(new URLSearchParams(q))

  it('sin texto, las de hoy en cualquier estado', () => {
    expect(de(consultaSalidas('  ', 2, '2026-11-24', rango))).toEqual({ estado: '', orden: 'fecha', porPagina: '100', empresa: '2', desde: '2026-11-24', hasta: '2026-11-24' })
  })

  it('con texto, en el rango del listado', () => {
    expect(de(consultaSalidas('flores', null, '2026-11-24', rango))).toMatchObject({ q: 'flores', desde: '2026-10-25', hasta: '2026-12-24' })
  })

  it('un número (id o bus), en cualquier fecha', () => {
    const q = de(consultaSalidas('3149', null, '2026-11-24', rango))
    expect(q.q).toBe('3149')
    expect(q.desde).toBeUndefined()
  })
})

describe('porCercania', () => {
  it('ordena por distancia a ahora; a igual distancia, la que viene', () => {
    const ahora = new Date('2026-11-24T12:00:00Z')
    const s = (id: number, fecha: string) => ({ id, fecha })
    const r = porCercania([s(1, '2026-11-20T12:00:00Z'), s(2, '2026-11-24T10:00:00Z'), s(3, '2026-11-24T14:00:00Z'), s(4, '2026-11-25T12:00:00Z')], ahora)
    expect(r.map((x) => x.id)).toEqual([3, 2, 4, 1])
  })
})
