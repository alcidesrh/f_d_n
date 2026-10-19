import { describe, expect, it } from 'vitest'
import { abreviar, aQuery, codigoAId, disponibilidad, errorArticulos, filtroInicial, guias, limpiarArticulos } from '../reglas'
import type { FilaEncomienda, PermisosEncomienda } from '../types'

const TODO: PermisosEncomienda = { ver: true, registrar: true, interna: true, embarcar: true, entregar: true, anular: true, sinFactura: true }
const GUATE = { id: 1, nombre: 'Guatemala' }
const FLORES = { id: 3, nombre: 'Flores' }

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
    remitente: { id: 1, nombre: 'Ana', nit: 'CF', telefono: null, documento: null },
    destinatario: { id: 2, nombre: 'Luis', nit: 'CF', telefono: null, documento: null },
    bultos: 1,
    contenido: 'Paquete 5 lb',
    total: { centavos: 3000, moneda: 'GTQ', texto: 'Q 30.00' },
    estadoFacturacion: 'certificada',
    venta: null,
    usuario: 'ana',
    ...parcial,
  }
}

describe('disponibilidad de acciones', () => {
  it('en bodega en su origen se embarca y se anula, no se entrega', () => {
    const a = disponibilidad(fila({}), TODO, GUATE.id)
    expect(a.embarcar.habilitada).toBe(true)
    expect(a.anular.habilitada).toBe(true)
    expect(a.entregar.habilitada).toBe(false)
    expect(a.desembarcar.habilitada).toBe(false)
  })

  it('desde otra estación no se embarca ni se anula', () => {
    const a = disponibilidad(fila({}), TODO, FLORES.id)
    expect(a.embarcar.motivo).toContain('Guatemala')
    expect(a.anular.habilitada).toBe(false)
  })

  it('desembarcada en su destino se entrega allí (u oficina)', () => {
    const f = fila({ estado: 'desembarcada', ubicacion: FLORES })
    expect(disponibilidad(f, TODO, FLORES.id).entregar.habilitada).toBe(true)
    expect(disponibilidad(f, TODO, null).entregar.habilitada).toBe(true)
    expect(disponibilidad(f, TODO, GUATE.id).entregar.habilitada).toBe(false)
    expect(disponibilidad(f, TODO, FLORES.id).embarcar.habilitada).toBe(false)
    // Ya viajó: no se anula.
    expect(disponibilidad(f, TODO, null).anular.habilitada).toBe(false)
  })

  it('la que va con boleto no se anula sola; a bordo se desembarca', () => {
    const a = disponibilidad(fila({ modalidad: 'con_boleto', estado: 'embarcada', ubicacion: null }), TODO, null)
    expect(a.anular.motivo).toContain('boleto')
    expect(a.desembarcar.habilitada).toBe(true)
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
