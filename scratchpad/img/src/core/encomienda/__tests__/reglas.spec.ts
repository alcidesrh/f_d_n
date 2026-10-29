import { describe, expect, it } from 'vitest'
import { abreviar, antiguedad, aQuery, codigoAId, disponibilidad, errorArticulos, filtroInicial, guias, limpiarArticulos, porDestino, resumenEntrega } from '../reglas'
import type { FilaEncomienda, PermisosEncomienda } from '../types'

const TODO: PermisosEncomienda = { ver: true, registrar: true, interna: true, embarcar: true, entregar: true, anular: true, especiales: true, sinFactura: true }
const GUATE = { id: 1, nombre: 'Guatemala' }
const FLORES = { id: 3, nombre: 'Flores' }

/** Lo que manda el backend (`FlujoEncomienda::acciones`) para una recibida en su origen. */
const EN_ORIGEN: FilaEncomienda['acciones'] = {
  embarcar: null,
  desembarcar: 'La encomienda está recibida: solo se desembarca una encomienda embarcada o en tránsito.',
  entregar: 'La encomienda está recibida: solo se entrega una encomienda desembarcada en su destino o en tránsito hacia él.',
  anular: null,
}
/** Y para una desembarcada en su destino. */
const EN_DESTINO: FilaEncomienda['acciones'] = {
  embarcar: 'La encomienda ya está en su destino: se entrega.',
  desembarcar: 'La encomienda está desembarcada: solo se desembarca una encomienda embarcada o en tránsito.',
  entregar: null,
  anular: 'Solo se anula una encomienda que no ha viajado (en la bodega de su origen).',
}

function fila(parcial: Partial<FilaEncomienda>): FilaEncomienda {
  return {
    id: 7,
    codigo: '00000007',
    creada: '2026-10-18T10:00:00-06:00',
    estado: 'recibida',
    modalidad: 'pagada',
    porCobrar: false,
    empresa: { id: 2, nombre: 'Maya de Oro', alias: 'MAYA' },
    origen: GUATE,
    destino: FLORES,
    ubicacion: GUATE,
    salida: null,
    ruta: null,
    remitente: { id: 1, nombre: 'Ana', nit: 'CF', telefono: null, documento: null },
    destinatario: { id: 2, nombre: 'Luis', nit: 'CF', telefono: null, documento: null },
    bultos: 1,
    contenido: 'Paquete 5 lb',
    total: { centavos: 3000, moneda: 'GTQ', texto: 'Q 30.00' },
    estadoFacturacion: 'certificada',
    venta: null,
    usuario: 'ana',
    acciones: EN_ORIGEN,
    ...parcial,
  }
}

describe('disponibilidad de acciones', () => {
  it('lo que admite la encomienda lo dice el backend, con su motivo', () => {
    const a = disponibilidad(fila({}), TODO, GUATE.id)
    expect(a.embarcar.habilitada).toBe(true)
    expect(a.anular.habilitada).toBe(true)
    expect(a.entregar).toEqual({ visible: true, habilitada: false, motivo: EN_ORIGEN.entregar })
    expect(a.desembarcar).toEqual({ visible: true, habilitada: false, motivo: EN_ORIGEN.desembarcar })
  })

  it('desde otra estación no se embarca ni se anula', () => {
    const a = disponibilidad(fila({}), TODO, FLORES.id)
    expect(a.embarcar.motivo).toBe('Está en la bodega de Guatemala')
    expect(a.anular.motivo).toBe('La anula la estación Guatemala')
  })

  it('desembarcada en su destino se entrega allí (u oficina)', () => {
    const f = fila({ estado: 'desembarcada', ubicacion: FLORES, acciones: EN_DESTINO })
    expect(disponibilidad(f, TODO, FLORES.id).entregar.habilitada).toBe(true)
    expect(disponibilidad(f, TODO, null).entregar.habilitada).toBe(true)
    expect(disponibilidad(f, TODO, GUATE.id).entregar.motivo).toBe('La entrega la estación Flores')
    expect(disponibilidad(f, TODO, FLORES.id).embarcar.motivo).toBe(EN_DESTINO.embarcar)
    expect(disponibilidad(f, TODO, null).anular.motivo).toBe(EN_DESTINO.anular)
  })

  it('el motivo del backend va antes que la estación', () => {
    const a = disponibilidad(fila({ estado: 'desembarcada', ubicacion: FLORES, acciones: EN_DESTINO }), TODO, GUATE.id)
    expect(a.embarcar.motivo).toBe(EN_DESTINO.embarcar)
    // Desembarcar no mira la estación: el backend comprueba que sea una parada de su salida.
    expect(disponibilidad(fila({ acciones: { ...EN_ORIGEN, desembarcar: null } }), TODO, FLORES.id).desembarcar.habilitada).toBe(true)
  })

  it('sin permiso la acción no se muestra', () => {
    const a = disponibilidad(fila({}), { ...TODO, anular: false, embarcar: false }, null)
    expect(a.anular.visible).toBe(false)
    expect(a.embarcar.visible).toBe(false)
    expect(a.consultar.visible).toBe(true)
  })
})

describe('artículos', () => {
  it('pide peso al paquete y tipo al especial', () => {
    expect(errorArticulos([])).toMatch(/al menos/)
    expect(errorArticulos([{ tipo: 'paquete', cantidad: 1, peso: null }])).toMatch(/peso/)
    expect(errorArticulos([{ tipo: 'especial', cantidad: 2, especial: null }])).toMatch(/especial/)
    expect(errorArticulos([{ tipo: 'paquete', cantidad: 0, peso: 3 }])).toMatch(/cantidad/)
    expect(errorArticulos([{ tipo: 'paquete', cantidad: 2, peso: 3 }, { tipo: 'especial', cantidad: 1, especial: 4 }])).toBeNull()
  })

  it('limpia lo que no va con el tipo', () => {
    expect(limpiarArticulos([{ tipo: 'especial', cantidad: 1, especial: 4, peso: 9, descripcion: '  ' }])).toEqual([{ tipo: 'especial', cantidad: 1, especial: 4, descripcion: null }])
  })

  it('una guía por bulto, numeradas de 1 a N', () => {
    const g = guias({
      articulos: [
        { tipo: 'paquete', cantidad: 2, peso: 5, descripcion: 'ropa', especial: null } as never,
        { tipo: 'especial', cantidad: 1, especial: { id: 1, nombre: 'Sobre' }, descripcion: null } as never,
      ],
    })
    expect(g.map((x) => `${x.numero}/${x.total} ${x.articulo}`)).toEqual(['1/3 Paquete 5 lb · ropa', '2/3 Paquete 5 lb · ropa', '3/3 Sobre'])
  })
})

describe('listado', () => {
  it('arma la consulta solo con lo filtrado', () => {
    expect(aQuery(filtroInicial(), 1, 25)).toBe('pagina=1&porPagina=25')
    expect(aQuery({ ...filtroInicial(), q: ' 123 ', estados: ['recibida', 'desembarcada'], destino: 3 }, 2, 50)).toBe('q=123&estados=recibida%2Cdesembarcada&destino=3&pagina=2&porPagina=50')
  })

  it('lee un código escaneado', () => {
    expect(codigoAId('00000123')).toBe(123)
    expect(codigoAId(' 45 ')).toBe(45)
    expect(codigoAId('abc')).toBeNull()
    expect(codigoAId('0')).toBeNull()
  })
})

describe('sigla del destino sin alias', () => {
  it('iniciales con tres palabras o más; si no, tres letras', () => {
    expect(abreviar('Santa Elena, Flores, Peten')).toBe('SEF')
    expect(abreviar('Guatemala')).toBe('GUA')
    expect(abreviar('Cruce de Morales')).toBe('CDM')
    expect(abreviar('Melchor de Mencos')).toBe('MDM')
    expect(abreviar('Poptun, Peten')).toBe('POP')
    expect(abreviar('Petén')).toBe('PET')
  })
})

describe('entrega múltiple y pendientes', () => {
  const PETEN = { id: 9, nombre: 'Poptún' }
  const filas = [
    fila({ id: 1, bultos: 2, porCobrar: true, total: { centavos: 4000, moneda: 'GTQ', texto: 'Q 40.00' } }),
    fila({ id: 2, bultos: 1 }),
    fila({ id: 3, bultos: 3, porCobrar: true, destino: PETEN, destinatario: { id: 5, nombre: 'Eva', nit: 'CF', telefono: null, documento: null } }),
  ]

  it('resume lo marcado: bultos, cobro y destinatarios distintos', () => {
    expect(resumenEntrega(filas, [1, 2])).toMatchObject({ cantidad: 2, bultos: 3, porCobrar: 1, cobrar: 4000 })
    expect(resumenEntrega(filas, [1, 2]).destinatarios.map((d) => d.nombre)).toEqual(['Luis'])
    expect(resumenEntrega(filas, [1, 3]).destinatarios).toHaveLength(2)
    expect(resumenEntrega(filas, []).cantidad).toBe(0)
  })

  it('cuenta los días de calendario desde que se registró', () => {
    expect(antiguedad('2026-10-18T23:50:00', new Date(2026, 9, 19, 0, 10))).toBe(1)
    expect(antiguedad('2026-10-19T08:00:00', new Date(2026, 9, 19, 20, 0))).toBe(0)
  })

  it('agrupa por destino en orden alfabético', () => {
    expect(porDestino(filas).map((g) => [g.destino.nombre, g.filas.map((f) => f.id)])).toEqual([
      ['Flores', [1, 2]],
      ['Poptún', [3]],
    ])
  })

})
