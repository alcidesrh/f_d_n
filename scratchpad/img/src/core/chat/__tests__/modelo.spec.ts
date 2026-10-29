import { describe, expect, it } from 'vitest'
import { agrupar, aplicarAviso, aplicarPresencia, coincide, etiquetaDia, iniciales, medidasReducidas, segmentos, tamanoLegible, unir, vistoPor } from '../modelo'
import type { Canal, Mensaje, Perfil } from '../types'

const ana: Perfil = { id: 1, nombre: 'Ana López', ambito: 'administracion', lugar: null }
const beto: Perfil = { id: 2, nombre: 'Beto', ambito: 'estacion', lugar: 'Flores' }

const msg = (id: number, autor: Perfil | null, fecha: string, texto = 'x'): Mensaje => ({ id, canal: 9, autor, texto, fecha, adjuntos: [], archivos: [], respuesta: null })

const canal = (id: number, extra: Partial<Canal> = {}): Canal => ({
  id,
  tipo: 'directo',
  nombre: 'Beto',
  contacto: beto,
  miembros: [ana, beto],
  noLeidos: 0,
  leidoHasta: 0,
  lecturas: [{ usuario: 2, hasta: 0 }],
  actividad: '2026-10-01T10:00:00Z',
  ultimo: null,
  ...extra,
})

describe('agrupar', () => {
  const ahora = new Date(2026, 9, 8, 12, 0)

  it('separa por día y junta los seguidos del mismo autor', () => {
    const bloques = agrupar(
      [
        msg(1, ana, new Date(2026, 9, 7, 9, 0).toISOString()),
        msg(2, beto, new Date(2026, 9, 8, 9, 0).toISOString()),
        msg(3, beto, new Date(2026, 9, 8, 9, 2).toISOString()),
        msg(4, ana, new Date(2026, 9, 8, 9, 3).toISOString()),
      ],
      1,
      ahora,
    )
    expect(bloques.map((b) => b.etiqueta)).toEqual(['Ayer', 'Hoy'])
    expect(bloques[1]!.rachas.map((r) => [r.autor?.id, r.mio, r.mensajes.length])).toEqual([
      [2, false, 2],
      [1, true, 1],
    ])
  })

  it('corta la racha si pasan más de cinco minutos', () => {
    const bloques = agrupar([msg(1, beto, new Date(2026, 9, 8, 9, 0).toISOString()), msg(2, beto, new Date(2026, 9, 8, 9, 6).toISOString())], 1, ahora)
    expect(bloques[0]!.rachas).toHaveLength(2)
  })
})

describe('etiquetaDia', () => {
  it('usa el nombre del día en la última semana y la fecha después', () => {
    const ahora = new Date(2026, 9, 8)
    expect(etiquetaDia(new Date(2026, 9, 5), ahora)).toMatch(/^Lunes$/)
    expect(etiquetaDia(new Date(2026, 8, 20), ahora)).toMatch(/20 de septiembre/)
  })
})

describe('aplicarAviso', () => {
  it('sube el canal, cuenta no leídos ajenos y no los del canal que se lee', () => {
    const canales = [canal(1), canal(2)]
    const aviso = { tipo: 'mensaje' as const, canal: 2, mensaje: 50, autor: beto, extracto: 'hola' }
    const fuera = aplicarAviso(canales, aviso, 1, null)!
    expect(fuera.map((c) => c.id)).toEqual([2, 1])
    expect(fuera[0]!.noLeidos).toBe(1)
    expect(fuera[0]!.ultimo?.extracto).toBe('hola')
    expect(aplicarAviso(canales, aviso, 1, 2)![0]!.noLeidos).toBe(0)
  })

  it('no cuenta mis propios mensajes ni repite uno ya aplicado', () => {
    const propio = aplicarAviso([canal(1)], { tipo: 'mensaje', canal: 1, mensaje: 5, autor: ana, extracto: 'yo' }, 1, null)!
    expect(propio[0]!.noLeidos).toBe(0)
    const viejo = [canal(1, { ultimo: { id: 9, autor: 2, extracto: 'a', fecha: '' } })]
    expect(aplicarAviso(viejo, { tipo: 'mensaje', canal: 1, mensaje: 9, autor: beto, extracto: 'a' }, 1, null)).toBe(viejo)
  })

  it('pide recargar si el canal es nuevo y limpia al leer en otro dispositivo', () => {
    expect(aplicarAviso([canal(1)], { tipo: 'mensaje', canal: 7, mensaje: 1, autor: beto, extracto: '' }, 1, null)).toBeNull()
    const leido = aplicarAviso([canal(1, { noLeidos: 3 })], { tipo: 'leido', canal: 1, usuario: 1, hasta: 40 }, 1, null)!
    expect(leido[0]).toMatchObject({ noLeidos: 0, leidoHasta: 40 })
  })
})

describe('utilidades', () => {
  it('une páginas sin duplicar y en orden', () => {
    expect(unir([msg(3, ana, ''), msg(4, ana, '')], [msg(1, ana, ''), msg(3, ana, '')]).map((m) => m.id)).toEqual([1, 3, 4])
  })

  it('busca sin acentos y por palabras', () => {
    expect(coincide('José Pérez · Estación Flores', 'jose flores')).toBe(true)
    expect(coincide('José Pérez', 'ana')).toBe(false)
  })

  it('iniciales', () => {
    expect(iniciales('Ana López Ruiz')).toBe('AL')
    expect(iniciales('beto')).toBe('B')
  })

  it('separa enlaces del texto sin la puntuación final', () => {
    expect(segmentos('ver https://fdn.gt/x?a=1. listo')).toEqual([{ texto: 'ver ' }, { url: 'https://fdn.gt/x?a=1' }, { texto: '. listo' }])
  })
})

describe('visto', () => {
  const cami: Perfil = { id: 3, nombre: 'Cami', ambito: 'estacion', lugar: null }
  const grupo = canal(1, { tipo: 'grupo', miembros: [ana, beto, cami], lecturas: [{ usuario: 2, hasta: 10 }, { usuario: 3, hasta: 4 }] })

  it('dice quiénes vieron un mensaje y si ya lo vieron todos', () => {
    expect(vistoPor(grupo, 4)).toMatchObject({ todos: true })
    const v = vistoPor(grupo, 8)
    expect(v.todos).toBe(false)
    expect(v.quienes.map((p) => p.id)).toEqual([2])
  })

  it('el aviso de lectura de otro actualiza su visto sin tocar mis no leídos', () => {
    const despues = aplicarAviso([grupo], { tipo: 'leido', canal: 1, usuario: 3, hasta: 12 }, 1, null)!
    expect(despues[0]!.lecturas).toContainEqual({ usuario: 3, hasta: 12 })
    expect(vistoPor(despues[0]!, 10).todos).toBe(true)
    expect(despues[0]!.noLeidos).toBe(grupo.noLeidos)
  })
})

describe('archivos', () => {
  it('tamaño legible', () => {
    expect(tamanoLegible(800)).toBe('800 B')
    expect(tamanoLegible(1536)).toBe('1.5 KB')
    expect(tamanoLegible(350 * 1024)).toBe('350 KB')
    expect(tamanoLegible(3.2 * 1024 * 1024)).toBe('3.2 MB')
  })

  it('reduce por el lado mayor y nunca agranda', () => {
    expect(medidasReducidas(4000, 3000, 1600)).toEqual({ ancho: 1600, alto: 1200 })
    expect(medidasReducidas(3000, 4000, 1600)).toEqual({ ancho: 1200, alto: 1600 })
    expect(medidasReducidas(800, 600, 1600)).toEqual({ ancho: 800, alto: 600 })
  })
})

describe('aplicarPresencia', () => {
  it('agrega al que se conecta sin repetirlo y quita al que se desconecta', () => {
    expect(aplicarPresencia([1], 2, true)).toEqual([1, 2])
    expect(aplicarPresencia([1, 2], 2, true)).toEqual([1, 2])
    expect(aplicarPresencia([1, 2], 1, false)).toEqual([2])
    expect(aplicarPresencia([], 5, false)).toEqual([])
  })
})
