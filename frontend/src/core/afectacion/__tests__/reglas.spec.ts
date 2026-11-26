import { describe, expect, it } from 'vitest'
import { busesPara, empresasPara, enQueVa, estacionesPara, faltaParaReportar, lapso, propuesta, reporteDe } from '../reglas'
import type { Afectacion, ParaReportar, TipoReportable } from '../tipos'

const impresora: TipoReportable = {
  id: 1,
  codigo: 'impresora_averiada',
  nombre: 'Impresora averiada',
  descripcion: null,
  sujeto: 'estacion',
  prioridad: 'urgente',
  empresas: [1],
  estaciones: [10, 11],
}
const averia: TipoReportable = {
  id: 2,
  codigo: 'averia_bus',
  nombre: 'Avería del bus',
  descripcion: null,
  sujeto: 'bus',
  prioridad: 'urgente',
  empresas: [1],
  estaciones: null,
}
const caido: TipoReportable = {
  id: 3,
  codigo: 'servicio_externo_caido',
  nombre: 'Servicio externo caído',
  descripcion: null,
  sujeto: 'empresa',
  prioridad: 'urgente',
  empresas: null,
  estaciones: null,
}

const datos: ParaReportar = {
  tipos: [impresora, averia, caido],
  estacion: { id: 11, nombre: 'Estación Norte' },
  empresas: [
    { id: 1, nombre: 'Transportes del Norte' },
    { id: 2, nombre: 'Rutas del Sur' },
  ],
  estaciones: [
    { id: 10, nombre: 'Estación Central' },
    { id: 11, nombre: 'Estación Norte' },
    { id: 12, nombre: 'Estación Sur' },
  ],
  buses: [
    { id: 5, codigo: '120', matricula: 'P-123', empresaId: 1 },
    { id: 6, codigo: '300', matricula: null, empresaId: 2 },
  ],
}

function afectacion(cambios: Partial<Afectacion> = {}): Afectacion {
  return {
    id: 1,
    tipo: { id: 1, codigo: 'impresora_averiada', nombre: 'Impresora averiada', prioridad: 'urgente', sujeto: 'estacion' },
    estado: 'pendiente',
    descripcion: 'No enciende',
    lugar: 'Estación Central',
    empresa: null,
    estacion: null,
    bus: null,
    reportadaPor: { id: 7, nombre: 'Juan Pérez' },
    automatica: false,
    creadaEn: '2026-11-26T08:00:00-06:00',
    enviadaEn: '2026-11-26T08:01:00-06:00',
    escaladaEn: null,
    ultimoAvisoEn: '2026-11-26T08:01:00-06:00',
    avisos: 1,
    proximoAviso: '2026-11-26T10:00:00-06:00',
    resueltaEn: null,
    resueltaPor: null,
    resolucion: null,
    puede: { editar: true, resolver: true },
    ...cambios,
  }
}

describe('afectaciones', () => {
  it('el formulario ofrece solo los lugares donde se puede reportar cada tipo', () => {
    expect(estacionesPara(impresora, datos).map((e) => e.id)).toEqual([10, 11])
    expect(estacionesPara(averia, datos)).toEqual([])
    expect(busesPara(averia, datos).map((b) => b.codigo)).toEqual(['120'])
    expect(busesPara(impresora, datos)).toEqual([])
    expect(empresasPara(caido, datos).map((e) => e.id)).toEqual([1, 2])
    expect(empresasPara(null, datos)).toEqual([])
  })

  it('propone la estación de trabajo y la única empresa', () => {
    expect(propuesta(impresora, datos)).toEqual({ empresa: 1, estacion: 11 })
    expect(propuesta({ ...impresora, estaciones: [10] }, datos)).toEqual({ empresa: 1, estacion: 10 })
    expect(propuesta({ ...impresora, estaciones: [10, 12] }, datos)).toEqual({ empresa: 1, estacion: null })
    expect(propuesta(caido, datos)).toEqual({ empresa: null, estacion: null })
  })

  it('dice qué falta para reportar', () => {
    expect(faltaParaReportar(null, { descripcion: 'x' }, datos)).toBe('Elija qué pasó.')
    expect(faltaParaReportar(impresora, { descripcion: '  ', estacion: 10 }, datos)).toBe('Cuente qué pasó.')
    expect(faltaParaReportar(impresora, { descripcion: 'No enciende' }, datos)).toBe('Elija la estación.')
    expect(faltaParaReportar(impresora, { descripcion: 'No enciende', estacion: 10 }, datos)).toBeNull()
    expect(faltaParaReportar(averia, { descripcion: 'No arranca' }, datos)).toBe('Elija el bus.')
    expect(faltaParaReportar(averia, { descripcion: 'No arranca', bus: 5 }, datos)).toBeNull()
    expect(faltaParaReportar(caido, { descripcion: 'Sin facturación' }, datos)).toBe('Elija la empresa.')
    expect(faltaParaReportar(caido, { descripcion: 'Sin facturación', empresa: 2 }, datos)).toBeNull()
  })

  it('envía solo lo que el tipo usa', () => {
    expect(reporteDe(averia, { descripcion: ' No arranca ', empresa: 2, estacion: 10, bus: 5 })).toEqual({
      tipo: 2,
      descripcion: 'No arranca',
      empresa: null,
      estacion: 10,
      bus: 5,
    })
    expect(reporteDe(caido, { descripcion: 'x', empresa: 2, estacion: 10 })).toEqual({ tipo: 3, descripcion: 'x', empresa: 2, estacion: null, bus: null })
  })

  it('pone en palabras en qué va el centinela', () => {
    const ahora = new Date('2026-11-26T09:30:00-06:00')
    expect(enQueVa(afectacion(), ahora)).toBe('Abierta hace 1 h · 1 aviso · próximo aviso en 30 min.')
    expect(enQueVa(afectacion({ estado: 'creada', avisos: 0, proximoAviso: '2026-11-26T09:30:00-06:00' }), ahora)).toBe(
      'Abierta hace 1 h · sin avisos entregados · avisa en el próximo minuto.',
    )
    expect(enQueVa(afectacion({ avisos: 4, escaladaEn: '2026-11-26T09:00:00-06:00' }), ahora)).toBe(
      'Abierta hace 1 h · 4 avisos, escalada · próximo aviso en 30 min.',
    )
    expect(
      enQueVa(afectacion({ estado: 'resuelta', resueltaEn: '2026-11-26T11:00:00-06:00', resueltaPor: { id: 8, nombre: 'Ana' }, proximoAviso: null }), ahora),
    ).toBe('Resuelta por Ana en 3 h.')
  })

  it('cuenta los lapsos como los avisos', () => {
    expect(lapso(-3)).toBe('0 min')
    expect(lapso(45)).toBe('45 min')
    expect(lapso(47 * 60 + 59)).toBe('47 h')
    expect(lapso(48 * 60)).toBe('2 días')
  })
})
