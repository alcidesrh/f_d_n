import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { AJUSTES, CLAVES, CLAVES_DEL_PRESET, GRUPOS, deFabrica, desdeAnterior, iguales, normalizar, variablesCss, variablesDeFabrica } from '../ajustes'

describe('catálogo', () => {
  it('cada ajuste tiene su valor de fábrica entre sus opciones, sin opciones repetidas', () => {
    const valores = (clave: (typeof CLAVES)[number]) => AJUSTES[clave].opciones.map((o) => o.valor as unknown)
    expect(CLAVES.filter((c) => !valores(c).includes(AJUSTES[c].fabrica))).toEqual([])
    expect(CLAVES.filter((c) => new Set(valores(c)).size !== valores(c).length)).toEqual([])
  })

  it('el editor muestra cada ajuste una sola vez', () => {
    const enGrupos = GRUPOS.flatMap((g) => g.claves)
    expect([...enGrupos].sort()).toEqual([...CLAVES].sort())
  })

  it('solo el modo, los colores y el estilo regeneran el preset', () => {
    expect([...CLAVES_DEL_PRESET].sort()).toEqual(['estilo', 'modo', 'primario', 'superficie'])
  })

  it('los valores de fábrica coinciden con los de `styles/tema/apariencia.css`', () => {
    // Vitest es la raíz del frontend (vitest.config.ts).
    const css = readFileSync(resolve(process.cwd(), 'src/styles/tema/apariencia.css'), 'utf8')
    const distintos = Object.entries(variablesDeFabrica()).filter(([nombre, valor]) => !css.includes(`${nombre}: ${valor};`))
    expect(distintos).toEqual([])
    const declaradas = [...css.matchAll(/(--apariencia-[a-z-]+):/g)].map((m) => m[1])
    expect(declaradas.sort(), 'sin parámetros que el catálogo no conozca').toEqual(Object.keys(variablesDeFabrica()).sort())
  })
})

describe('normalizar', () => {
  const base = deFabrica('dark')

  it('toma de la entrada solo opciones vigentes; lo demás, de la base', () => {
    const out = normalizar({ primario: 'red', esquinas: 0, densidad: 7, estilo: 'otro', ajena: 1 }, base)
    expect(out.primario).toBe('red')
    expect(out.esquinas).toBe(0)
    expect(out.densidad).toBe(base.densidad)
    expect(out.estilo).toBe(base.estilo)
    expect(out).not.toHaveProperty('ajena')
    expect(out.modo).toBe('dark')
  })

  it('cualquier cosa que no sea un objeto da la base', () => {
    expect(normalizar(null, base)).toEqual(base)
    expect(normalizar('x', base)).toEqual(base)
  })
})

describe('desdeAnterior', () => {
  it('traduce lo que guardaba la store `ui`', () => {
    const ajustes = normalizar(desdeAnterior({ mode: 'dark', primary: 'teal', surface: 'zinc', preset: 'aura', otro: 1 }), deFabrica())
    expect(ajustes).toMatchObject({ modo: 'dark', primario: 'teal', superficie: 'zinc', estilo: 'aura' })
    expect(desdeAnterior(null)).toEqual({})
  })
})

describe('variablesCss', () => {
  it('quita de <html> los de fábrica y escribe los demás', () => {
    const ajustes = { ...deFabrica(), esquinas: 0, texto: 17, contraste: 'alto' as const, fuente: 'sistema' as const }
    const vars = variablesCss(ajustes)
    expect(vars['--apariencia-esquinas']).toBe('0')
    expect(vars['--apariencia-texto']).toBe('17px')
    expect(vars['--apariencia-contraste']).toBe('1')
    expect(vars['--apariencia-fuente']).toContain('system-ui')
    expect(vars['--apariencia-densidad']).toBeNull()
    expect(Object.values(variablesCss(deFabrica())).every((v) => v === null)).toBe(true)
  })
})

describe('iguales', () => {
  it('compara ajuste por ajuste', () => {
    expect(iguales(deFabrica(), deFabrica())).toBe(true)
    expect(iguales(deFabrica(), { ...deFabrica(), sombras: 0 })).toBe(false)
  })
})
