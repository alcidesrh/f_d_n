import { describe, expect, it } from 'vitest'
import { ErrorPublico } from '../api'
import { mensajeDeError } from '../errores'
import { idiomaDelNavegador, IDIOMAS } from '../i18n'
import de from '../i18n/mensajes/de'
import en from '../i18n/mensajes/en'
import es from '../i18n/mensajes/es'
import fr from '../i18n/mensajes/fr'
import it_ from '../i18n/mensajes/it'
import { head, headHtml } from '../seo'

/** Todas las claves (a.b.c) de un objeto de mensajes. */
const claves = (o: object, prefijo = ''): string[] =>
  Object.entries(o).flatMap(([k, v]) => (typeof v === 'object' && v ? claves(v, `${prefijo}${k}.`) : [`${prefijo}${k}`]))

describe('idiomas', () => {
  it('elige el primer idioma del navegador que la página tiene', () => {
    expect(idiomaDelNavegador(['de-AT', 'en'])).toBe('de')
    expect(idiomaDelNavegador(['pt-BR', 'it-IT'])).toBe('it')
    expect(idiomaDelNavegador(['ja'])).toBe('es')
    expect(idiomaDelNavegador([])).toBe('es')
  })

  it('las cinco traducciones tienen las mismas claves y ninguna vacía', () => {
    const base = claves(es).sort()
    for (const m of [en, fr, de, it_]) expect(claves(m).sort()).toEqual(base)
    const vacias = (o: object): string[] => claves(o).filter((k) => k.split('.').reduce<any>((x, p) => x[p], o) === '' && !k.endsWith('descripcion'))
    for (const m of [es, en, fr, de, it_]) expect(vacias(m)).toEqual([])
  })
})

describe('mensajes de error', () => {
  const t = (clave: string, v?: Record<string, unknown>) => `${clave}${v && Object.keys(v).length ? JSON.stringify(v) : ''}`
  const te = (clave: string) => ['errores.pago_rechazado', 'errores.venta_cerrada', 'errores.general', 'errores.detalleBanco'].includes(clave)

  it('traduce por código y agrega el motivo del banco', () => {
    const e = new ErrorPublico('El banco rechazó la transacción: fondos insuficientes.', 'pago_rechazado', 402)
    expect(mensajeDeError(e, t, te)).toEqual({
      titulo: 'errores.pago_rechazado',
      detalle: 'errores.detalleBanco{"mensaje":"El banco rechazó la transacción: fondos insuficientes."}',
    })
  })

  it('sin traducción usa el mensaje del backend', () => {
    expect(mensajeDeError(new ErrorPublico('Algo raro', 'codigo_nuevo', 422), t, te)).toEqual({ titulo: 'Algo raro', detalle: null })
    expect(mensajeDeError(new ErrorPublico('', 'venta_cerrada', 422), t, te).titulo).toBe('errores.venta_cerrada')
  })
})

it('head con canónica y versiones en cada idioma', () => {
  const h = head((k) => k, { idioma: 'fr', pagina: 'servicios', ruta: 'servicios', origen: 'https://ejemplo.gt/', indexable: true })
  expect(h.canonica).toBe('https://ejemplo.gt/pagina/fr/servicios')
  expect(h.alternas.map((a) => a.idioma)).toEqual([...IDIOMAS, 'x-default'])
  const html = headHtml({ ...h, titulo: 'A "B" <C>', indexable: false })
  expect(html).toContain('<title>A &quot;B&quot; &lt;C&gt;</title>')
  expect(html).toContain('content="noindex"')
  expect(html).toContain('hreflang="x-default" href="https://ejemplo.gt/pagina/es/servicios"')
})
