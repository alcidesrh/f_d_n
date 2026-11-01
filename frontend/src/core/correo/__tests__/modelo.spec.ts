import { describe, expect, it } from 'vitest'
import { SIN_FILTROS, aplicarLocal, consulta, corto, documentoAislado, fechaLista, hayFiltros, leerDirecciones, mismaRedaccion, quien, redaccionVacia, tamano, tieneImagenesRemotas } from '../modelo'
import type { Resumen } from '../types'

const fila = (id: number, cambios: Partial<Resumen> = {}): Resumen => ({
  id,
  carpeta: 'entrada',
  de: { direccion: 'juan@gmail.com', nombre: 'Juan Pérez' },
  para: [{ direccion: 'ana@sistemafdn.com', nombre: 'Ana' }],
  asunto: 'Hola',
  extracto: '',
  fecha: '2026-11-01T10:00:00-06:00',
  leido: false,
  destacado: false,
  externo: true,
  conAdjuntos: false,
  envio: null,
  ...cambios,
})

describe('consulta del listado', () => {
  it('lleva la vista y solo los filtros activos', () => {
    expect(consulta('entrada', SIN_FILTROS)).toBe('carpeta=entrada')
    expect(consulta('destacados', { noLeidos: true, adjuntos: true, origen: 'externos', q: ' ventas ' }, 40)).toBe('carpeta=destacados&noLeidos=1&adjuntos=1&origen=externos&q=ventas&antes=40')
    expect(hayFiltros(SIN_FILTROS)).toBe(false)
    expect(hayFiltros({ ...SIN_FILTROS, q: 'x' })).toBe(true)
  })
})

describe('direcciones', () => {
  it('lee lo escrito y deja lo inválido', () => {
    expect(leerDirecciones('Ana <Ana@X.com>, juan@y.com; nada, juan@y.com')).toEqual({
      direcciones: [{ direccion: 'ana@x.com', nombre: 'Ana' }, { direccion: 'juan@y.com', nombre: null }],
      resto: 'nada',
    })
  })

  it('nombres cortos y quién se muestra', () => {
    expect(corto({ direccion: 'beto@x.com', nombre: null })).toBe('beto')
    expect(corto({ direccion: 'a@x.com', nombre: 'Ana López' })).toBe('Ana')
    expect(quien(fila(1))).toBe('Juan Pérez')
    expect(quien(fila(1, { carpeta: 'enviados', para: [1, 2, 3, 4].map((n) => ({ direccion: `u${n}@x.com`, nombre: null })) }))).toBe('Para: u1, u2, u3 +1')
  })
})

describe('fechas y tamaños', () => {
  const ahora = new Date('2026-11-05T15:00:00')
  it('hoy la hora, ayer, este año el día', () => {
    expect(fechaLista('2026-11-05T09:05:00', ahora)).toMatch(/9:05/)
    expect(fechaLista('2026-11-04T09:05:00', ahora)).toBe('Ayer')
    expect(fechaLista('2026-03-02T09:05:00', ahora)).toMatch(/2/)
  })
  it('tamaño legible', () => {
    expect([tamano(500), tamano(2048), tamano(3 * 1024 * 1024 + 200_000)]).toEqual(['500 B', '2 KB', '3,2 MB'])
  })
})

describe('redacción', () => {
  it('vacía y comparación', () => {
    const r = { para: [], cc: [], cco: [], asunto: ' ', texto: '\n' }
    expect(redaccionVacia(r)).toBe(true)
    expect(redaccionVacia({ ...r, texto: 'hola' })).toBe(false)
    expect(mismaRedaccion(r, { ...r })).toBe(true)
  })
})

describe('cambios locales de la lista', () => {
  const lista = [fila(1), fila(2, { destacado: true }), fila(3, { leido: true })]
  it('marca sin sacar de la vista', () => {
    const r = aplicarLocal(lista, [1], 'leido', 'entrada', SIN_FILTROS)
    expect(r.map((c) => c.leido)).toEqual([true, false, true])
  })
  it('saca lo que deja de pertenecer', () => {
    expect(aplicarLocal(lista, [1, 2], 'papelera', 'entrada', SIN_FILTROS).map((c) => c.id)).toEqual([3])
    expect(aplicarLocal(lista, [2], 'quitarDestacado', 'destacados', SIN_FILTROS).map((c) => c.id)).toEqual([1, 3])
    expect(aplicarLocal(lista, [1], 'leido', 'entrada', { ...SIN_FILTROS, noLeidos: true }).map((c) => c.id)).toEqual([2, 3])
    expect(aplicarLocal(lista, [3], 'restaurar', 'papelera', SIN_FILTROS).map((c) => c.id)).toEqual([1, 2])
  })
})

describe('documento aislado del HTML', () => {
  it('sin imágenes remotas salvo que se pidan', () => {
    const sin = documentoAislado('<p>Hola</p>', false, 'https://fdn.gt')
    expect(sin).toContain("img-src https://fdn.gt data:;")
    expect(sin).toContain("default-src 'none'")
    expect(sin).toContain('<base target="_blank">')
    expect(documentoAislado('<p/>', true, 'https://fdn.gt')).toContain('img-src https://fdn.gt data: https: http:')
    expect(tieneImagenesRemotas('<img src="https://t.co/p.gif">')).toBe(true)
    expect(tieneImagenesRemotas('<img src="data:image/png;base64,xx">')).toBe(false)
  })
})
