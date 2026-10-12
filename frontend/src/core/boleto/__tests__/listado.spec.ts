import { describe, expect, it } from 'vitest'
import { aQuery, alternarOrden, disponibilidad, documentoTexto, filtroInicial, filtrosActivos, paginaInicial } from '../listado'
import type { BoletoFila, PuedeBoleto } from '../types'

const TODO: PuedeBoleto = { cancelar: true, anular: true, reasignar: true, chequear: true }
const AHORA = new Date('2026-10-11T12:00:00-06:00')

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
    ...cambios,
  }
}

describe('listado de boletos', () => {
  it('arma la query sin los filtros vacíos', () => {
    const f = { ...filtroInicial('2026-10-01', '2026-10-31'), id: 7, estados: ['emitido', 'chequeado'] as const, serie: '  ', origen: ' Quetzal ', clienteBoleto: { id: 3, label: 'Ana' } }
    expect(aQuery({ ...f, estados: [...f.estados] }, paginaInicial())).toBe('desde=2026-10-01&hasta=2026-10-31&id=7&estado=emitido%2Cchequeado&origen=Quetzal&clienteBoleto=3&pagina=1&porPagina=15&orden=id&dir=desc')
  })

  it('cuenta los filtros aplicados sin el rango', () => {
    expect(filtrosActivos(filtroInicial('2026-10-01', '2026-10-31'))).toBe(0)
    expect(filtrosActivos({ ...filtroInicial(), estados: ['anulado'], asiento: 0, clienteDocumento: { id: 1, label: 'x' } })).toBe(3)
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
    const a = disponibilidad(null, { ...TODO, cancelar: false }, AHORA)
    expect(a.consultar).toEqual({ visible: true, habilitada: false, motivo: 'Elija un boleto de la lista' })
    expect(a.cancelar.visible).toBe(false)
    expect(a.anular.visible).toBe(true)
  })

  it('un emitido de una salida programada se cancela, anula y reasigna pero no se chequea', () => {
    const a = disponibilidad(boleto(), TODO, AHORA)
    expect(a.cancelar.habilitada).toBe(true)
    expect(a.anular.habilitada).toBe(true)
    expect(a.reasignar.habilitada).toBe(true)
    expect(a.chequear).toMatchObject({ habilitada: false, motivo: 'Se chequea con la salida abordando o iniciada' })
    expect(a.correo.habilitada).toBe(true)
  })

  it('pasada la hora de salida ya no se anula ni reasigna', () => {
    const a = disponibilidad(boleto({ fecha: '2026-10-11T08:00:00-06:00', salidaEstado: 'abordando' }), TODO, AHORA)
    expect(a.anular).toMatchObject({ habilitada: false, motivo: 'La salida ya pasó su hora' })
    expect(a.reasignar.habilitada).toBe(false)
    expect(a.chequear.habilitada).toBe(true)
  })

  it('un chequeado se cancela pero no se anula; un anulado no se cancela ni se reenvía', () => {
    const chequeado = disponibilidad(boleto({ estado: 'chequeado', salidaEstado: 'abordando' }), TODO, AHORA)
    expect(chequeado.cancelar.habilitada).toBe(true)
    expect(chequeado.anular.motivo).toBe('El boleto está chequeado: solo se opera un boleto emitido')

    const anulado = disponibilidad(boleto({ estado: 'anulado' }), TODO, AHORA)
    expect(anulado.cancelar.motivo).toBe('El boleto está anulado: solo se cancela un boleto emitido o chequeado')
    expect(anulado.correo.habilitada).toBe(false)
    expect(anulado.consultar.habilitada).toBe(true)

    const finalizada = disponibilidad(boleto({ salidaEstado: 'finalizada' }), TODO, AHORA)
    expect(finalizada.cancelar.motivo).toBe('La salida está finalizada')
  })

  it('solo un cancelado se revierte, salvo cortesía, agencia o salida cerrada', () => {
    expect(disponibilidad(boleto({ estado: 'cancelado', salidaEstado: 'iniciada' }), TODO, AHORA).revertir.habilitada).toBe(true)
    expect(disponibilidad(boleto(), TODO, AHORA).revertir.motivo).toBe('El boleto está emitido: solo se revierte un boleto cancelado')
    expect(disponibilidad(boleto({ estado: 'cancelado', documento: { tipo: 'cortesia', serie: null, numero: null } }), TODO, AHORA).revertir.motivo).toBe('Las cortesías canceladas no se revierten')
    expect(disponibilidad(boleto({ estado: 'cancelado', canal: 'agencia' }), TODO, AHORA).revertir.motivo).toBe('Los boletos de agencia cancelados no se revierten')
    expect(disponibilidad(boleto({ estado: 'cancelado', salidaEstado: 'finalizada' }), TODO, AHORA).revertir.motivo).toBe('La salida está finalizada')
    expect(disponibilidad(boleto({ estado: 'cancelado' }), { ...TODO, cancelar: false }, AHORA).revertir.visible).toBe(false)
  })

  it('una venta en proceso no admite operaciones', () => {
    const a = disponibilidad(boleto({ ventaConfirmada: false }), TODO, AHORA)
    expect(a.anular.motivo).toBe('La venta todavía se está procesando')
    expect(a.factura.habilitada).toBe(false)
  })
})
