import { describe, expect, it } from 'vitest'
import { aNuevoDeposito, bonoPrevisto, disponibilidadDeposito, erroresDeposito, filtroDepositosInicial, filtrosDepositosActivos, queryDepositos, quetzales, type BorradorDeposito } from '../depositos'
import type { DepositoFila } from '../types'

const q = (centavos: number) => ({ centavos, moneda: 'GTQ', texto: quetzales(centavos) })

function deposito(cambios: Partial<DepositoFila> = {}): DepositoFila {
  return {
    id: 1,
    fecha: '2026-10-10',
    agencia: { id: 67, nombre: 'Palasan Tour' },
    estado: 'registrado',
    importe: q(50000),
    aplicaBono: true,
    bono: q(5000),
    numeroBoleta: 'B-1',
    observacion: null,
    motivoRechazo: null,
    creadoEn: '2026-10-12T09:00:00-06:00',
    creadoPor: 'agencia1',
    resueltoEn: null,
    resueltoPor: null,
    ...cambios,
  }
}

const borrador = (cambios: Partial<BorradorDeposito> = {}): BorradorDeposito => ({
  agencia: 67,
  fecha: '2026-10-12',
  importe: 500.5,
  numeroBoleta: ' 778 ',
  aplicaBono: true,
  observacion: '  ',
  ...cambios,
})

describe('query de depósitos', () => {
  it('sin filtros solo pide la página (el backend usa ±1 mes)', () => {
    expect(queryDepositos(filtroDepositosInicial(), 1, 15)).toBe('pagina=1&porPagina=15')
  })

  it('un solo día es desde y hasta; estados separados por coma', () => {
    const f = { ...filtroDepositosInicial(), desde: '2026-10-01', agencia: 67, boleta: ' 44 ', estados: ['registrado', 'rechazado'] as const }
    const p = new URLSearchParams(queryDepositos({ ...f, estados: [...f.estados] }, 2, 25))
    expect(Object.fromEntries(p)).toEqual({ desde: '2026-10-01', hasta: '2026-10-01', agencia: '67', boleta: '44', estado: 'registrado,rechazado', pagina: '2', porPagina: '25' })
  })

  it('cuenta los filtros activos', () => {
    expect(filtrosDepositosActivos(filtroDepositosInicial())).toBe(0)
    expect(filtrosDepositosActivos({ ...filtroDepositosInicial(), id: 3, boleta: 'x', estados: ['acreditado'] })).toBe(3)
  })
})

describe('acciones del depósito elegido', () => {
  it('sin elegir o sin permiso no se habilitan', () => {
    expect(disponibilidadDeposito(null, true).acreditar).toMatchObject({ visible: true, habilitada: false })
    expect(disponibilidadDeposito(deposito(), false).rechazar).toMatchObject({ visible: false, habilitada: false })
  })

  it('solo un registrado se acredita o rechaza', () => {
    expect(disponibilidadDeposito(deposito(), true).acreditar).toEqual({ visible: true, habilitada: true, motivo: null })
    expect(disponibilidadDeposito(deposito({ estado: 'acreditado' }), true).rechazar).toEqual({ visible: true, habilitada: false, motivo: 'El depósito ya está acreditado' })
  })
})

describe('registro', () => {
  it('valida cada campo', () => {
    expect(erroresDeposito(borrador(), '2026-10-12')).toEqual({})
    expect(Object.keys(erroresDeposito(borrador({ agencia: null, fecha: '2026-10-13', importe: 0, numeroBoleta: ' ' }), '2026-10-12')).sort()).toEqual(['agencia', 'fecha', 'importe', 'numeroBoleta'])
    expect(erroresDeposito(borrador({ numeroBoleta: '9'.repeat(31) }), '2026-10-12').numeroBoleta).toMatch('30')
  })

  it('arma el cuerpo en centavos y sin observación vacía', () => {
    expect(aNuevoDeposito(borrador())).toEqual({ agencia: 67, fecha: '2026-10-12', importe: 50050, numeroBoleta: '778', aplicaBono: true })
    expect(aNuevoDeposito(borrador({ observacion: ' ok ', importe: 0.1 + 0.2 })).importe).toBe(30)
  })

  it('bono previsto como el backend (hacia abajo)', () => {
    expect(bonoPrevisto(50000, '10.00', true)).toBe(5000)
    expect(bonoPrevisto(5, '10.00', true)).toBe(0)
    expect(bonoPrevisto(12345, '7.5', true)).toBe(925)
    expect(bonoPrevisto(50000, '10.00', false)).toBe(0)
    expect(bonoPrevisto(50000, null, true)).toBe(0)
  })

  it('formatea quetzales', () => {
    expect(quetzales(123456)).toBe('Q 1,234.56')
    expect(quetzales(5, 'USD')).toBe('USD 0.05')
  })
})
