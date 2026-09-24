import { describe, expect, it, vi } from 'vitest'
import type { FormFieldSource } from '@/utils/formkit/schemaSerializer'
import { createIconRelationResolver, isIconRelation, type IconGateway } from '../iconRelation'

function field(overrides: Partial<FormFieldSource> & Pick<FormFieldSource, 'name' | 'namedType'>): FormFieldSource {
  return { kind: 'SCALAR', required: false, isList: false, isRelation: false, enumValues: [], ...overrides }
}

const iconField = field({ name: 'icon', namedType: 'Icon', kind: 'OBJECT', isRelation: true })
const fields = [field({ name: 'nombre', namedType: 'String' }), iconField]

/** Gateway en memoria: `db` es IRI → nombre de ícono. */
function fakeGateway(db: Record<string, string> = {}) {
  let seq = Object.keys(db).length
  const gateway: IconGateway = {
    iconName: vi.fn<IconGateway['iconName']>(async (iri: string) => db[iri] ?? null),
    findIri: vi.fn<IconGateway['findIri']>(async (name: string) => Object.keys(db).find((iri) => db[iri] === name) ?? null),
    create: vi.fn<IconGateway['create']>(async (name: string) => {
      const iri = `/api/icons/${++seq}`
      db[iri] = name
      return iri
    }),
  }
  return gateway
}

describe('isIconRelation', () => {
  it('solo relaciones a-uno con destino Icon', () => {
    expect(isIconRelation(iconField)).toBe(true)
    expect(isIconRelation({ ...iconField, isList: true })).toBe(false)
    expect(isIconRelation(field({ name: 'bus', namedType: 'Bus', isRelation: true }))).toBe(false)
    expect(isIconRelation(field({ name: 'icon', namedType: 'String' }))).toBe(false)
  })
})

describe('createIconRelationResolver', () => {
  it('hydrate: IRI → nombre del ícono, sin tocar otros campos', async () => {
    const resolver = createIconRelationResolver(fakeGateway({ '/api/icons/1': 'bus' }))
    expect(await resolver.hydrate(fields, { nombre: 'Rutas', icon: '/api/icons/1' })).toEqual({
      nombre: 'Rutas',
      icon: 'bus',
    })
  })

  it('hydrate: si el IRI no se puede leer conserva el IRI', async () => {
    const gateway = fakeGateway()
    vi.mocked(gateway.iconName).mockRejectedValueOnce(new Error('red'))
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const resolver = createIconRelationResolver(gateway)
    expect(await resolver.hydrate(fields, { icon: '/api/icons/9' })).toEqual({ icon: '/api/icons/9' })
    warn.mockRestore()
  })

  it('resolve: ícono sin cambios reutiliza el IRI hidratado sin consultar', async () => {
    const gateway = fakeGateway({ '/api/icons/1': 'bus' })
    const resolver = createIconRelationResolver(gateway)
    await resolver.hydrate(fields, { icon: '/api/icons/1' })
    expect(await resolver.resolve(fields, { icon: 'bus' })).toEqual({ icon: '/api/icons/1' })
    expect(gateway.findIri).not.toHaveBeenCalled()
    expect(gateway.create).not.toHaveBeenCalled()
  })

  it('resolve: reutiliza un Icon existente con ese nombre', async () => {
    const gateway = fakeGateway({ '/api/icons/1': 'bus', '/api/icons/2': 'settings' })
    const resolver = createIconRelationResolver(gateway)
    expect(await resolver.resolve(fields, { nombre: 'x', icon: 'settings' })).toEqual({
      nombre: 'x',
      icon: '/api/icons/2',
    })
    expect(gateway.create).not.toHaveBeenCalled()
  })

  it('resolve: crea el Icon si no existe, una sola vez por nombre', async () => {
    const gateway = fakeGateway({ '/api/icons/1': 'bus' })
    const resolver = createIconRelationResolver(gateway)
    expect(await resolver.resolve(fields, { icon: 'map-pin' })).toEqual({ icon: '/api/icons/2' })
    expect(await resolver.resolve(fields, { icon: 'map-pin' })).toEqual({ icon: '/api/icons/2' })
    expect(gateway.create).toHaveBeenCalledTimes(1)
    expect(gateway.create).toHaveBeenCalledWith('map-pin')
  })

  it('resolve: vacío → null, IRI y ausencia se dejan tal cual', async () => {
    const resolver = createIconRelationResolver(fakeGateway())
    expect(await resolver.resolve(fields, { icon: '' })).toEqual({ icon: null })
    expect(await resolver.resolve(fields, { icon: null })).toEqual({ icon: null })
    expect(await resolver.resolve(fields, { icon: '/api/icons/5' })).toEqual({ icon: '/api/icons/5' })
    expect(await resolver.resolve(fields, { nombre: 'a' })).toEqual({ nombre: 'a' })
  })
})
