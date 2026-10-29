import { describe, expect, it } from 'vitest'
import { cifrasEncomienda, encomiendaQuery, errorEncomienda, filtroEncomiendaInicial, nombreArchivo, REPORTES_ENCOMIENDA, type FiltroEncomienda, type OpcionesReporteEncomienda } from '../encomienda'

const OPCIONES: OpcionesReporteEncomienda = { hoy: '2026-10-19', estaciones: [], enclaves: [], empresas: [], alcance: { estacion: 4, empresa: null }, caja: true }
const base = (parcial: Partial<FiltroEncomienda> = {}): FiltroEncomienda => ({ ...filtroEncomiendaInicial(OPCIONES), ...parcial })
const dia = (d: number) => new Date(2026, 9, d)

describe('reportes de encomiendas', () => {
  it('arranca en hoy con la estación del usuario', () => {
    const f = filtroEncomiendaInicial(OPCIONES)
    expect(f.estacion).toBe(4)
    expect(f.rango?.map((d) => d?.getDate())).toEqual([19, 19])
  })

  it('cada reporte manda solo lo que usa', () => {
    const f = base({ empresa: 2, origen: 7, destino: 9, soloPorCobrar: true })
    expect(encomiendaQuery(REPORTES_ENCOMIENDA.cuadre, f)).toBe('desde=2026-10-19&estacion=4&empresa=2')
    expect(encomiendaQuery(REPORTES_ENCOMIENDA['detalle-general'], f)).toBe('desde=2026-10-19&hasta=2026-10-19&empresa=2&origen=7&destino=9&soloPorCobrar=1')
    expect(encomiendaQuery(REPORTES_ENCOMIENDA['pendientes-entrega'], f)).toBe('estacion=4&empresa=2&soloPorCobrar=1')
  })

  it('con el segundo día pendiente, el rango es de un día', () => {
    expect(encomiendaQuery(REPORTES_ENCOMIENDA['detalle-factura'], base({ rango: [dia(3), null] }))).toBe('desde=2026-10-03&hasta=2026-10-03&estacion=4')
  })

  it('valida la fecha y el tope del rango', () => {
    expect(errorEncomienda(REPORTES_ENCOMIENDA.cuadre, base({ rango: null }))).toBe('Elige la fecha.')
    expect(errorEncomienda(REPORTES_ENCOMIENDA['guia-interna'], base({ rango: [new Date(2026, 6, 1), dia(19)] }))).toMatch(/62 días/)
    expect(errorEncomienda(REPORTES_ENCOMIENDA['pendientes-entrega'], base({ rango: null }))).toBeNull()
    expect(encomiendaQuery(REPORTES_ENCOMIENDA.cuadre, base({ rango: null }))).toBeNull()
  })

  it('nombra el archivo por reporte y fechas', () => {
    expect(nombreArchivo('cuadre', base(), '2026-10-19')).toBe('encomiendas_cuadre_20261019')
    expect(nombreArchivo('detalle-general', base({ rango: [dia(1), dia(19)] }), '2026-10-19')).toBe('encomiendas_detalle_general_20261001_20261019')
    expect(nombreArchivo('pendientes-entrega', base(), '2026-10-19')).toBe('encomiendas_pendientes_entrega_20261019')
  })

  it('arma las cifras de la vista previa', () => {
    const c = cifrasEncomienda('cuadre', { ingreso: 31000, facturado: 20000, facturadas: 2, cobrado: 11000, cobradas: 1, anuladas: 1 })
    expect(c[0]).toMatchObject({ etiqueta: 'Ingreso del día', destacada: true })
    expect(String(c[0]!.valor)).toContain('310.00')
    expect(c.find((x) => x.etiqueta === 'Anuladas')).toMatchObject({ valor: 1, alerta: true })
  })
})
