import { describe, expect, it } from 'vitest'
import { aQuery, alternarOrden, disponibilidad, documentoTexto, filtroInicial, filtrosActivos, paginaInicial } from '../listado'
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

  it('cuenta los filtros aplicados sin el rango', () => {
    expect(filtrosActivos(filtroInicial('2026-10-01', '2026-10-31'))).toBe(0)
    expect(filtrosActivos({ ...filtroInicial(), estados: ['anulado'], asiento: 0, origen: 4 })).toBe(3)
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
    const a = disponibilidad(boleto(), { ...TODO, anular: false })
    expect(a.anular).toMatchObject({ visible: false, habilitada: false })
    expect(a.reasignar.visible).toBe(true)
  })

  it('un anulado no se reenvía ni se reimprime; una venta en proceso tampoco', () => {
    const anulado = disponibilidad(boleto({ estado: 'anulado' }), TODO)
    expect(anulado.correo).toMatchObject({ habilitada: false, motivo: 'El boleto está anulado' })
    expect(anulado.consultar.habilitada).toBe(true)

    const enProceso = disponibilidad(boleto({ ventaConfirmada: false }), TODO)
    expect(enProceso.factura.motivo).toBe('La venta todavía se está procesando')
  })
})
