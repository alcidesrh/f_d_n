import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import type { EntityFieldSchema, EntitySchema, SchemaInputField } from '@/core/graphql/types'

function entityField(name: string, overrides: Partial<EntityFieldSchema> = {}): EntityFieldSchema {
  return {
    name,
    type: 'String',
    namedType: 'String',
    kind: 'SCALAR',
    required: false,
    isList: false,
    isRelation: false,
    isSubcollection: false,
    enumValues: [],
    ...overrides,
  }
}

function input(name: string, overrides: Partial<SchemaInputField> = {}): SchemaInputField {
  return { name, type: 'String', namedType: 'String', kind: 'SCALAR', required: false, isList: false, isRelation: false, enumValues: [], ...overrides }
}

const inputs = [input('nombre'), input('descripcion'), input('ruta', { namedType: 'Ruta', isRelation: true })]
const busEntity = {
  name: 'Bus',
  queryItem: 'bus',
  queryCollection: 'buses',
  fields: [entityField('id', { namedType: 'ID' }), entityField('nombre'), entityField('descripcion'), entityField('createdAt'), entityField('ruta', { namedType: 'Ruta', kind: 'OBJECT', isRelation: true })],
  create: { kind: 'create', inputFields: inputs },
  update: { kind: 'update', inputFields: [input('id', { namedType: 'ID' }), ...inputs] },
} as unknown as EntitySchema

const { busStore, rutaStore } = vi.hoisted(() => {
  const make = () => ({
    formFields: [] as Array<Record<string, unknown>>,
    columns: [] as unknown[],
    init: vi.fn<() => Promise<void>>(async () => {}),
    fetchItem: vi.fn<(id: string | number, fields?: string[]) => Promise<Record<string, unknown>>>(),
    create: vi.fn<(data: Record<string, unknown>) => Promise<Record<string, unknown>>>(async (d) => ({ id: '/api/buses/99', ...d })),
    update: vi.fn<(data: Record<string, unknown>) => Promise<Record<string, unknown>>>(async (d) => d),
    loadFullList: vi.fn<() => Promise<unknown[]>>(async () => [{ value: '/api/rutas/1', label: 'Norte' }]),
  })
  return { busStore: make(), rutaStore: make() }
})

vi.mock('@/core/entities/schema', () => ({
  useSchemaRepositoryStore: () => ({ getEntityMetadata: () => busEntity }),
}))
vi.mock('@/core/notify', () => ({ notify: { error: vi.fn<(text: string) => void>() } }))
vi.mock('@/core/entities/registry', () => ({
  getEntity: (name: string) => (name === 'Ruta' ? rutaStore : busStore),
  useEntityRegistry: () => ({ getEntity: (name: string) => (name === 'Ruta' ? rutaStore : busStore) }),
}))

const { useEntityForm } = await import('@/features/entity-crud/form/useEntityForm')

/** Nombres de los inputs del schema FormKit (recorre el árbol anidado). */
function inputNames(nodes: unknown[]): string[] {
  const out: string[] = []
  const walk = (node: unknown) => {
    if (!node || typeof node !== 'object') return
    const record = node as Record<string, unknown>
    if (record.$formkit && typeof record.name === 'string') out.push(record.name)
    if (Array.isArray(record.children)) record.children.forEach(walk)
  }
  nodes.forEach(walk)
  return out
}

function valueOf(nodes: unknown[], name: string): unknown {
  let found: unknown
  const walk = (node: unknown) => {
    if (!node || typeof node !== 'object') return
    const record = node as Record<string, unknown>
    if (record.$formkit && record.name === name) found = record.value
    if (Array.isArray(record.children)) record.children.forEach(walk)
  }
  nodes.forEach(walk)
  return found
}

function labelOf(nodes: unknown[], name: string): unknown {
  let found: unknown
  const walk = (node: unknown) => {
    if (!node || typeof node !== 'object') return
    const record = node as Record<string, unknown>
    if (record.$formkit && record.name === name) found = record.label
    if (Array.isArray(record.children)) record.children.forEach(walk)
  }
  nodes.forEach(walk)
  return found
}

describe('useEntityForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    busStore.formFields = []
  })

  it('sin formFields usa los fields del schema que acepta la mutación (alta)', async () => {
    const form = useEntityForm('Bus')
    await flushPromises()
    expect(busStore.init).toHaveBeenCalled()
    expect(form.mode.value).toBe('create')
    expect(inputNames(form.schema.value)).toEqual(['nombre', 'descripcion', 'ruta'])
    expect(busStore.fetchItem).not.toHaveBeenCalled()
  })

  it('con id carga el registro pidiendo solo los formFields visibles y envía update con el IRI', async () => {
    busStore.formFields = [
      { field: 'ruta', position: 2, visible: true, label: 'Ruta asignada' },
      { field: 'nombre', position: 1, visible: true },
      { field: 'descripcion', position: 3, visible: false },
    ]
    busStore.fetchItem.mockResolvedValue({ id: '/api/buses/5', nombre: 'Bus 5', ruta: { id: '/api/rutas/1', label: 'Norte' } })

    const id = ref<string | null>('5')
    const form = useEntityForm('Bus', { id })
    await flushPromises()

    expect(form.mode.value).toBe('update')
    expect(busStore.fetchItem).toHaveBeenCalledWith('5', ['nombre', 'ruta'])
    expect(inputNames(form.schema.value)).toEqual(['nombre', 'ruta'])
    expect(valueOf(form.schema.value, 'nombre')).toBe('Bus 5')
    expect(valueOf(form.schema.value, 'ruta')).toBe('/api/rutas/1')
    expect(labelOf(form.schema.value, 'ruta')).toBe('Ruta asignada')

    await form.submit({ nombre: 'Bus cinco', ruta: '/api/rutas/1' })
    expect(busStore.update).toHaveBeenCalledWith({ nombre: 'Bus cinco', ruta: '/api/rutas/1', id: '/api/buses/5' })
    expect(busStore.create).not.toHaveBeenCalled()
  })

  it('al quitar el id vuelve a modo alta sin cargar registro', async () => {
    busStore.fetchItem.mockResolvedValue({ id: '/api/buses/5', nombre: 'Bus 5' })
    const id = ref<string | null>('5')
    const form = useEntityForm('Bus', { id })
    await flushPromises()
    id.value = null
    await flushPromises()
    expect(form.mode.value).toBe('create')
    expect(valueOf(form.schema.value, 'nombre')).toBeUndefined()
    await form.submit({ nombre: 'Nuevo' })
    expect(busStore.create).toHaveBeenCalledWith({ nombre: 'Nuevo' })
  })

  it('si el registro no existe muestra error y no arma el formulario', async () => {
    busStore.fetchItem.mockResolvedValue(null as unknown as Record<string, unknown>)
    const form = useEntityForm('Bus', { id: '404' })
    await flushPromises()
    expect(form.schema.value).toEqual([])
    expect(form.error.value).toContain('No existe Bus con id 404')
  })
})
