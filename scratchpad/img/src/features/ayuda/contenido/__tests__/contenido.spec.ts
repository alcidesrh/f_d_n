/**
 * El manual en sí: ids únicos, temas que existen, referencias que resuelven y
 * que nunca llevan a una guía que quien ve la actual no podría abrir, y que la
 * ayuda para todos no habla de capacidades.
 */
import { describe, expect, it } from 'vitest'
import type { MiAcceso } from '@/core/acceso/tipos'
import { referenciasDe, veGuia, veSiVeOrigen } from '@/core/ayuda/reglas'
import { textosDe } from '@/core/ayuda/reglas'
import { GUIAS, TEMAS } from '../index'

const ids = new Set(GUIAS.map((g) => g.id))
const temas = new Set(TEMAS.map((t) => t.id))

/** Lo que ve alguien con solo estos accesos (sin fechas ni ámbitos: basta con tenerlos). */
const con = (...capacidades: string[]): MiAcceso => ({
  usuario: { id: 1, username: 'u' },
  sistema: false,
  capacidades: Object.fromEntries(
    capacidades.map((c) => [c, [{ empresas: null, estaciones: null, agencia: null }]]),
  ),
  recursos: {},
  agencia: null,
  estacionTrabajo: null,
  estaciones: [],
  huella: 'h',
})

const titulosVisibles = (acceso: MiAcceso | null) =>
  GUIAS.filter((g) => veGuia(g, acceso)).map((g) => g.titulo)

describe('contenido del manual', () => {
  it('cada guía tiene un id único y un tema que existe', () => {
    expect(ids.size).toBe(GUIAS.length)
    expect(GUIAS.filter((g) => !temas.has(g.tema)).map((g) => g.id)).toEqual([])
  })

  it('cada tema tiene al menos una guía que alguien puede ver', () => {
    expect(TEMAS.filter((t) => !GUIAS.some((g) => g.tema === t.id)).map((t) => t.id)).toEqual([])
  })

  it('las referencias entre guías resuelven', () => {
    const rotas = GUIAS.flatMap((g) =>
      referenciasDe(g)
        .filter((r) => !ids.has(r))
        .map((r) => `${g.id} → ${r}`),
    )
    expect(rotas).toEqual([])
  })

  it('una guía nunca enlaza a otra que quien la ve no podría abrir', () => {
    const expuestas = GUIAS.flatMap((g) =>
      referenciasDe(g)
        .map((r) => GUIAS.find((x) => x.id === r))
        .filter((destino) => destino && !veSiVeOrigen(g, destino))
        .map((destino) => `${g.id} → ${destino!.id}`),
    )
    expect(expuestas).toEqual([])
  })

  it('cada permiso se escribe como código de operación (área.acción)', () => {
    const codigos = GUIAS.flatMap((g) =>
      g.para.flatMap((a) => (typeof a.exige === 'string' ? [a.exige] : [...a.exige])),
    )
    expect(codigos.filter((c) => !/^[a-z_]+\.[a-z_]+$/.test(c))).toEqual([])
  })

  it('el manual no habla de capacidades (eso es asunto del código, no del usuario)', () => {
    const menciones = GUIAS.filter((g) =>
      textosDe(g).some((t) => t.toLowerCase().includes('capacidad')),
    ).map((g) => g.id)
    expect(menciones).toEqual([])
  })
})

describe('lo que ve cada cual', () => {
  it('sin accesos, solo las guías de todos; ninguna de operación', () => {
    const vistos = titulosVisibles(null)
    expect(vistos).toContain('Entrar al sistema')
    expect(vistos).not.toContain('Editar un usuario')
    expect(vistos).not.toContain('Anular un boleto')
    expect(vistos).not.toContain('Emitir una cortesía')
    expect(vistos).not.toContain('Darle accesos a un usuario')
    expect(GUIAS.filter((g) => veGuia(g, null)).every((g) => g.para.length === 0)).toBe(true)
  })

  it('quien solo vende ve la venta, no la anulación ni la edición de usuarios', () => {
    const vistos = titulosVisibles(con('venta.vender'))
    expect(vistos).toContain('Vender boletos en taquilla')
    expect(vistos).not.toContain('Anular un boleto')
    expect(vistos).not.toContain('Editar un usuario')
    expect(vistos).not.toContain('Crear un usuario')
  })

  it('editar un usuario lo ven solo quienes pueden editar usuarios', () => {
    expect(titulosVisibles(con('usuario.editar'))).toContain('Editar un usuario')
    expect(titulosVisibles(con('iam.asignar'))).not.toContain('Editar un usuario')
    expect(titulosVisibles(con('iam.roles'))).not.toContain('Editar un usuario')
  })

  it('quien no tiene una operación no ve su guía ni la de los enlaces que la nombran', () => {
    const vistos = titulosVisibles(con('boleto.ver'))
    expect(vistos).toContain('Buscar boletos')
    expect(vistos).not.toContain('Anular un boleto')
    expect(vistos).not.toContain('Reasignar un boleto')
  })
})
