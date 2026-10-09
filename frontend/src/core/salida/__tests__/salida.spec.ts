import { describe, expect, it } from 'vitest'
import { aFechaHora, aQuery, filtroInicial, filtrosActivos } from '../filtro'
import { aEsquema, aPayload, conEsquema, diasProgramados, difiereDeEsquema, errores, fechaHasta, formInicial, totalSalidas, type ProgramadorForm } from '../programacion'
import { omitidasPorMotivo, resumen } from '../resultado'
import { busesCompatibles, etiquetaCroquis, filtroBusVacio, filtroDeBus } from '../buses'
import type { BusOpcion, Esquema, ResultadoOperacion } from '../types'

const hoy = new Date(2026, 9, 2, 9, 0)

const form = (extra: Partial<ProgramadorForm> = {}): ProgramadorForm => ({
  ...formInicial(hoy),
  trayectoId: 7,
  momentos: [
    { hora: '6:05', busId: 1 },
    { hora: '14:30', busId: 2 },
  ],
  desde: '2026-10-05',
  ...extra,
})

const esquema: Esquema = {
  id: 3,
  nombre: 'Xela mañana',
  trayecto: { id: 7, origen: 'Xela', destino: 'Guatemala', ruta: 'Xela → Guatemala', duracionMinutos: 240 },
  intervaloDias: 2,
  momentos: [
    { hora: '06:05', busId: 1, bus: '95' },
    { hora: '14:30', busId: 2, bus: '98' },
  ],
  actualizadoEn: '2026-10-01T10:00:00-06:00',
  actualizadoPor: 'ana',
}

describe('programador de salidas', () => {
  it('un solo día por defecto', () => {
    const f = form()
    expect(fechaHasta(f)).toBeNull()
    expect(diasProgramados(f)).toEqual(['2026-10-05'])
    expect(errores(f, hoy)).toEqual([])
    expect(aPayload(f)).toEqual({
      trayectoId: 7,
      momentos: [
        { hora: '06:05', busId: 1 },
        { hora: '14:30', busId: 2 },
      ],
      desde: '2026-10-05',
      hasta: null,
      intervaloDias: 1,
    })
  })

  it('N días con intervalo calcula la fecha final', () => {
    const f = form({ repeticion: 'veces', veces: 4, intervaloDias: 2 })
    expect(fechaHasta(f)).toBe('2026-10-11')
    expect(diasProgramados(f)).toEqual(['2026-10-05', '2026-10-07', '2026-10-09', '2026-10-11'])
    expect(totalSalidas(f)).toBe(8)
    expect(aPayload(f).intervaloDias).toBe(2)
  })

  it('hasta una fecha (incluida), cruzando de mes', () => {
    const f = form({ repeticion: 'hasta', hasta: '2026-11-02', intervaloDias: 3 })
    const dias = diasProgramados(f)
    expect(dias[0]).toBe('2026-10-05')
    expect(dias[dias.length - 1]).toBe('2026-11-01')
    expect(dias).toHaveLength(10)
  })

  it('valida antes de pedir la vista previa', () => {
    expect(errores(form({ trayectoId: null }), hoy)).toContain('Elija el trayecto.')
    expect(errores(form({ momentos: [{ hora: '25:00', busId: 1 }] }), hoy)[0]).toMatch(/no es válida/)
    expect(errores(form({ momentos: [{ hora: '07:00', busId: null }] }), hoy)[0]).toMatch(/Elija el bus/)
    expect(errores(form({ momentos: [{ hora: '7:00', busId: 1 }, { hora: '07:00', busId: 1 }] }), hoy)[0]).toMatch(/dos veces/)
    expect(errores(form({ desde: '2026-10-01' }), hoy)).toContain('El día ya pasó.')
    expect(errores(form({ repeticion: 'hasta', hasta: '2026-10-04' }), hoy)).toContain('La fecha final es anterior al primer día.')
    expect(errores(form({ repeticion: 'hasta', hasta: null }), hoy)).toContain('Elija hasta qué fecha se repite.')
    expect(errores(form({ guardarComo: '  ' }), hoy)).toContain('Escriba el nombre del esquema a guardar.')
    const muchas = form({ repeticion: 'veces', veces: 365, momentos: Array.from({ length: 10 }, (_, i) => ({ hora: `${i + 10}:00`, busId: i + 1 })) })
    expect(errores(muchas, hoy).slice(-1)[0]).toMatch(/Serían 3650 salidas/)
  })

  it('manda el nombre del esquema solo si se pide guardarlo', () => {
    expect(aPayload(form({ guardarComo: ' Xela ' })).guardarComo).toBe('Xela')
    expect('guardarComo' in aPayload(form())).toBe(false)
  })

  it('carga un esquema y detecta cambios', () => {
    const f = conEsquema(form({ trayectoId: null, momentos: [] }), esquema)
    expect(f.trayectoId).toBe(7)
    expect(f.momentos).toEqual([
      { hora: '06:05', busId: 1 },
      { hora: '14:30', busId: 2 },
    ])
    expect(f.repeticion).toBe('veces')
    expect(difiereDeEsquema(f, esquema)).toBe(false)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '6:05', busId: 1 }, { hora: '14:30', busId: 2 }] }, esquema)).toBe(false)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '06:05', busId: 3 }, { hora: '14:30', busId: 2 }] }, esquema)).toBe(true)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '06:05', busId: 1 }] }, esquema)).toBe(true)
    expect(aEsquema(f, ' Nuevo ')).toEqual({ nombre: 'Nuevo', trayectoId: 7, intervaloDias: 2, momentos: [{ hora: '06:05', busId: 1 }, { hora: '14:30', busId: 2 }] })
  })
})

describe('filtro de salidas', () => {
  it('por defecto: sin finalizadas ni anuladas, próximas primero', () => {
    const q = new URLSearchParams(aQuery(filtroInicial()))
    expect(q.get('estado')).toBe('iniciada,abordando,programada')
    expect(q.get('orden')).toBe('proximas')
    expect(q.has('direccion')).toBe(false)
    expect(q.has('empresa')).toBe(false)
  })

  it('solo manda lo que tiene valor', () => {
    const f = { ...filtroInicial(), estados: [], empresa: 2, desde: '2026-10-01', orden: 'fecha' as const, direccion: 'desc' as const }
    const q = new URLSearchParams(aQuery(f))
    expect(q.get('estado')).toBe('')
    expect(q.get('empresa')).toBe('2')
    expect(q.get('desde')).toBe('2026-10-01')
    expect(q.get('direccion')).toBe('desc')
    expect(filtrosActivos(f)).toBe(2)
  })

  it('fecha y hora local para la edición', () => {
    expect(aFechaHora(new Date(2026, 11, 20, 9, 5))).toBe('2026-12-20T09:05')
  })
})

describe('resultado de una operación', () => {
  const ref = (id: number) => ({ id, fecha: '2026-12-20T09:00:00-06:00', ruta: 'Xela → Guatemala', bus: '95' })

  it('todo aplicado', () => {
    expect(resumen({ aplicadas: [ref(1), ref(2)], omitidas: [] }, 'anular')).toEqual({ texto: '2 salidas anuladas.', severidad: 'success' })
  })

  it('omitidas por asientos y conflicto', () => {
    const r: ResultadoOperacion = {
      aplicadas: [ref(1)],
      omitidas: [
        { ...ref(2), motivo: 'asientos', asientos: 3 },
        { ...ref(3), motivo: 'conflicto' },
        { ...ref(4), motivo: 'asientos', asientos: 1 },
      ],
    }
    expect(resumen(r, 'editar')).toEqual({ texto: '1 salida actualizada; 3 quedaron sin cambios.', severidad: 'warning' })
    const grupos = omitidasPorMotivo(r)
    expect(grupos.map((g) => [g.motivo, g.salidas.map((s) => s.id)])).toEqual([
      ['asientos', [2, 4]],
      ['conflicto', [3]],
    ])
  })

  it('nada aplicado es error', () => {
    expect(resumen({ aplicadas: [], omitidas: [{ ...ref(2), motivo: 'historial' }] }, 'eliminar').severidad).toBe('error')
  })
})

describe('elegir bus por croquis y clase (ADR-027)', () => {
  const bus = (id: number, croquisId: number | null, claseId: number | null): BusOpcion => ({ id, codigo: String(id), matricula: null, empresaId: 1, croquisId, claseId })
  const buses = [bus(1, 10, 1), bus(2, 10, 2), bus(3, 11, 1), bus(4, null, null)]

  it('sin filtro, todos', () => {
    expect(busesCompatibles(buses, filtroBusVacio()).map((b) => b.id)).toEqual([1, 2, 3, 4])
  })

  it('por croquis y por clase, combinables', () => {
    expect(busesCompatibles(buses, { croquisId: 10, claseId: null }).map((b) => b.id)).toEqual([1, 2])
    expect(busesCompatibles(buses, { croquisId: null, claseId: 1 }).map((b) => b.id)).toEqual([1, 3])
    expect(busesCompatibles(buses, { croquisId: 10, claseId: 1 }).map((b) => b.id)).toEqual([1])
  })

  it('conserva el bus ya elegido aunque no cumpla', () => {
    expect(busesCompatibles(buses, { croquisId: 11, claseId: null }, 2).map((b) => b.id)).toEqual([2, 3])
  })

  it('el filtro de un bus es su croquis y su clase', () => {
    expect(filtroDeBus(buses[1])).toEqual({ croquisId: 10, claseId: 2 })
    expect(filtroDeBus(null)).toEqual(filtroBusVacio())
  })

  it('etiqueta del croquis', () => {
    expect(etiquetaCroquis({ asientos: 47, asientosB: 0, plantas: 1 })).toBe('47 asientos')
    expect(etiquetaCroquis({ asientos: 60, asientosB: 12, plantas: 2 })).toBe('60 asientos · 12 B · 2 plantas')
    expect(etiquetaCroquis({ asientos: 30, asientosB: 30, plantas: 1 })).toBe('30 asientos · todos B')
  })
})
