import { describe, expect, it } from 'vitest'
import { aFechaHora, aQuery, filtroInicial, filtrosActivos } from '../filtro'
import { aEsquema, aPayload, automatizacion, conEsquema, diasProgramados, difiereDeEsquema, errores, fechaHasta, formInicial, redondearHora, totalSalidas, type ProgramadorForm } from '../programacion'
import { agruparRutas } from '../rutas'
import { omitidasPorMotivo, resumen } from '../resultado'
import { busesPara, duracionConClase, etiquetaCroquis, sumarMinutos } from '../buses'
import type { BusOpcion, Esquema, ResultadoOperacion } from '../types'

const hoy = new Date(2026, 9, 2, 9, 0)

const form = (extra: Partial<ProgramadorForm> = {}): ProgramadorForm => ({
  ...formInicial(hoy),
  rutaId: 7,
  momentos: [
    { hora: '6:05', croquisId: 4, busId: 1 },
    { hora: '14:30', croquisId: 4, busId: 2 },
  ],
  desde: '2026-10-05',
  ...extra,
})

const esquema: Esquema = {
  id: 3,
  nombre: 'Xela mañana',
  ruta: { id: 7, nombre: 'Xela → Guatemala', origen: 'Xela', destino: 'Guatemala', paradas: 5, duracionMinutos: 240 },
  intervaloDias: 2,
  momentos: [
    { hora: '06:05', busId: 1, bus: '95' },
    { hora: '14:30', busId: 2, bus: '98' },
  ],
  automatico: false,
  horizonteDias: 90,
  ancla: null,
  programadoHasta: null,
  pausadoMotivo: null,
  pausadoEn: null,
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
      rutaId: 7,
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
    expect(errores(form({ rutaId: null }), hoy)).toContain('Elija la ruta.')
    expect(errores(form({ momentos: [{ hora: '25:00', croquisId: null, busId: 1 }] }), hoy)[0]).toMatch(/no es válida/)
    expect(errores(form({ momentos: [{ hora: '07:00', croquisId: 4, busId: null }] }), hoy)[0]).toMatch(/Elija el bus/)
    expect(errores(form({ momentos: [{ hora: '7:00', croquisId: 4, busId: 1 }, { hora: '07:00', croquisId: 4, busId: 1 }] }), hoy)[0]).toMatch(/dos veces/)
    expect(errores(form({ desde: '2026-10-01' }), hoy)).toContain('El día ya pasó.')
    expect(errores(form({ repeticion: 'hasta', hasta: '2026-10-04' }), hoy)).toContain('La fecha final es anterior al primer día.')
    expect(errores(form({ repeticion: 'hasta', hasta: null }), hoy)).toContain('Elija hasta qué fecha se repite.')
    expect(errores(form({ guardarComo: '  ' }), hoy)).toContain('Escriba el nombre del esquema a guardar.')
    const muchas = form({ repeticion: 'veces', veces: 365, momentos: Array.from({ length: 10 }, (_, i) => ({ hora: `${i + 10}:00`, croquisId: 4, busId: i + 1 })) })
    expect(errores(muchas, hoy).slice(-1)[0]).toMatch(/Serían 3650 salidas/)
  })

  it('manda el nombre del esquema solo si se pide guardarlo', () => {
    expect(aPayload(form({ guardarComo: ' Xela ' })).guardarComo).toBe('Xela')
    expect('guardarComo' in aPayload(form())).toBe(false)
  })

  it('carga un esquema (con la distribución de cada bus) y detecta cambios', () => {
    const f = conEsquema(form({ rutaId: null, momentos: [] }), esquema, (busId) => (busId === 1 ? 4 : 5))
    expect(f.rutaId).toBe(7)
    expect(f.momentos).toEqual([
      { hora: '06:05', croquisId: 4, busId: 1 },
      { hora: '14:30', croquisId: 5, busId: 2 },
    ])
    expect(f.repeticion).toBe('veces')
    expect(difiereDeEsquema(f, esquema)).toBe(false)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '6:05', croquisId: null, busId: 1 }, { hora: '14:30', croquisId: null, busId: 2 }] }, esquema)).toBe(false)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '06:05', croquisId: 4, busId: 3 }, { hora: '14:30', croquisId: 5, busId: 2 }] }, esquema)).toBe(true)
    expect(difiereDeEsquema({ ...f, momentos: [{ hora: '06:05', croquisId: 4, busId: 1 }] }, esquema)).toBe(true)
    expect(aEsquema(f, ' Nuevo ')).toEqual({ nombre: 'Nuevo', rutaId: 7, intervaloDias: 2, momentos: [{ hora: '06:05', busId: 1 }, { hora: '14:30', busId: 2 }] })
    // El modo automático se cambia con el esquema guardado, no con lo que se edita en el formulario.
    expect(automatizacion(esquema, true, 400, '2026-10-05')).toEqual({
      nombre: 'Xela mañana',
      rutaId: 7,
      intervaloDias: 2,
      momentos: [{ hora: '06:05', busId: 1 }, { hora: '14:30', busId: 2 }],
      automatico: true,
      horizonteDias: 180,
      ancla: '2026-10-05',
    })
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

describe('distribución y bus de una salida (ADR-027, ADR-031)', () => {
  const bus = (id: number, croquisId: number | null, empresaId: number | null = 1): BusOpcion => ({ id, codigo: String(id), matricula: null, empresaId, croquisId, claseId: null })
  const buses = [bus(1, 10), bus(2, 10, 2), bus(3, 11), bus(4, null)]

  it('sin distribución, todos; con distribución, los de ese molde', () => {
    expect(busesPara(buses, { croquisId: null }).map((b) => b.id)).toEqual([1, 2, 3, 4])
    expect(busesPara(buses, { croquisId: 10 }).map((b) => b.id)).toEqual([1, 2])
  })

  it('filtra por empresa si se pide', () => {
    expect(busesPara(buses, { croquisId: 10, empresaId: 1 }).map((b) => b.id)).toEqual([1])
  })

  it('conserva el bus ya elegido aunque no cumpla', () => {
    expect(busesPara(buses, { croquisId: 11 }, 2).map((b) => b.id)).toEqual([2, 3])
  })

  it('duración según la clase del bus y hora de llegada', () => {
    const ruta = { duracionMinutos: 360, duracionGeneral: 300, duracionPorClase: { '3': 240 } }
    expect(duracionConClase(ruta, 3)).toBe(240)
    expect(duracionConClase(ruta, 9)).toBe(300)
    expect(duracionConClase(ruta, null)).toBe(300)
    expect(duracionConClase({ duracionMinutos: 360 }, null)).toBe(360)
    expect(sumarMinutos('06:30', 240)).toEqual({ hora: '10:30', dias: 0 })
    expect(sumarMinutos('22:15', 300)).toEqual({ hora: '03:15', dias: 1 })
  })

  it('etiqueta del croquis', () => {
    expect(etiquetaCroquis({ asientos: 47, asientosB: 0, plantas: 1 })).toBe('47 asientos')
    expect(etiquetaCroquis({ asientos: 60, asientosB: 12, plantas: 2 })).toBe('60 asientos · 12 B · 2 plantas')
    expect(etiquetaCroquis({ asientos: 30, asientosB: 30, plantas: 1 })).toBe('30 asientos · todos B')
  })
})

describe('agruparRutas', () => {
  const r = (id: number, origen: string, destino: string) => ({ id, nombre: `${origen} - ${destino}`, origen, destino })
  const rutas = [r(1, 'Guatemala', 'Flores'), r(2, 'Santa Elena', 'Guatemala'), r(3, 'Guatemala', 'Santa Elena'), r(4, 'Cobán', 'Santa Elena'), r(5, 'Cobán', 'Fray')]

  it('sin búsqueda agrupa todo por origen', () => {
    expect(agruparRutas(rutas).map((g) => [g.origen, g.rutas.map((x) => x.id)])).toEqual([
      ['Cobán', [5, 4]],
      ['Guatemala', [1, 3]],
      ['Santa Elena', [2]],
    ])
  })

  it('busca por palabras sueltas, sin acentos, y pone primero lo que más coincide', () => {
    const g = agruparRutas(rutas, 'guatem  santa ELENA')
    expect(g[0]).toEqual({ origen: 'Guatemala', rutas: [rutas[2], rutas[0]] })
    expect(g.map((x) => x.origen)).toEqual(['Guatemala', 'Santa Elena', 'Cobán'])
    expect(g.flatMap((x) => x.rutas).map((x) => x.id)).not.toContain(5)
    expect(agruparRutas(rutas, 'coban').flatMap((x) => x.rutas).map((x) => x.id)).toEqual([5, 4])
  })
})

describe('redondearHora', () => {
  it('lleva la hora al múltiplo de 5 minutos más cercano', () => {
    expect(redondearHora('6:07')).toBe('06:05')
    expect(redondearHora('06:08')).toBe('06:10')
    expect(redondearHora('23:58')).toBe('23:55')
    expect(redondearHora('xx')).toBe('xx')
  })
})
