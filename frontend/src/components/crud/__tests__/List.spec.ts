import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  DOMWrapper,
  flushPromises,
  mount,
  type ComponentMountingOptions,
  type VueWrapper,
} from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/formkit.config'
import type { AgnosticOption, EntitySchema } from '@/lib/apollo/types'
import type { CollectionFieldConfig, EntityStore } from '@/stores/entities/types'
import List from '@/components/crud/List.vue'

if (typeof window.matchMedia !== 'function') {
  window.matchMedia = (query: string) =>
    ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    }) as MediaQueryList
}

const { schemaRepoMock, registryMock } = vi.hoisted(() => ({
  schemaRepoMock: { getEntity: vi.fn<(name: string) => EntitySchema | null>() },
  registryMock: { getEntity: vi.fn<(name: string) => EntityStore>() },
}))

vi.mock('@/stores/schemaRepository', () => ({ useSchemaRepositoryStore: () => schemaRepoMock }))
vi.mock('@/composables/useEntityRegistry', () => ({ useEntityRegistry: () => registryMock }))

interface IconItem {
  id: string
  name: string
  icon: string
  description: string
  category: { id: string; label: string } | null
}

const columns: CollectionFieldConfig[] = [
  { field: 'id', label: 'ID', filterable: false },
  { field: 'name', label: 'Nombre', filterable: true },
  { field: 'icon', label: 'Icono', filterable: true },
  { field: 'description', label: 'Descripción', filterable: true },
  { field: 'category', label: 'Categoría', filterable: true },
]

const items: IconItem[] = [
  {
    id: '/api/icons/1',
    name: 'home',
    icon: 'pi pi-home',
    description: 'Inicio',
    category: { id: '/api/categories/1', label: 'Navegación' },
  },
]

function field(name: string, namedType: string, isRelation = false) {
  return {
    name,
    type: namedType,
    namedType,
    kind: 'SCALAR',
    required: false,
    isList: false,
    isRelation,
    isSubcollection: false,
  }
}

function arg(name: string) {
  return { name, type: 'String', namedType: 'String', required: false, isList: false }
}

const iconSchema: EntitySchema = {
  name: 'Icon',
  queryItem: 'icon',
  queryCollection: 'icons',
  collectionKind: 'list',
  collectionType: null,
  paginationType: null,
  orderInput: null,
  orderFields: [],
  itemArgs: [],
  collectionArgs: [],
  filterArgs: [arg('icon'), arg('name')],
  fields: [
    field('id', 'ID'),
    field('name', 'String'),
    field('icon', 'String'),
    field('description', 'String'),
    field('category', 'Category', true),
  ],
  scalarFields: ['id', 'name', 'icon', 'description'],
  relations: [field('category', 'Category', true)],
  subcollections: [],
  create: null,
  update: null,
  delete: null,
}

function makeStore(): EntityStore<IconItem> {
  const base = {
    $id: 'entity:Icon',
    $state: undefined as never,
    $patch: vi.fn<(partial: unknown) => void>(),
    $reset: vi.fn<() => void>(),
    $dispose: vi.fn<() => void>(),
    name: 'Icon',
    columns: [],
    items: [...items],
    pagination: {
      itemsPerPage: 10,
      currentPage: 1,
      totalCount: 1,
      lastPage: 1,
      hasNextPage: false,
    },
    filters: {},
    order: [],
    item: null,
    fullList: [],
    loadColumns: vi.fn<() => Promise<CollectionFieldConfig[]>>(async () => {
      store.columns = [...columns]
      return store.columns
    }),
    fetchItems: vi.fn<() => Promise<IconItem[]>>(async () => items),
    fetchItem: vi.fn<(id: string | number) => Promise<IconItem>>(
      async (id) => ({ ...items[0], id }) as IconItem,
    ),
    create: vi.fn<(data: Record<string, unknown>) => Promise<IconItem>>(
      async (data) => ({ ...items[0], ...data }) as IconItem,
    ),
    update: vi.fn<(data: Record<string, unknown>) => Promise<IconItem>>(
      async (data) => ({ ...items[0], ...data }) as IconItem,
    ),
    remove: vi.fn<(id: string | number) => Promise<IconItem>>(
      async (id) => ({ ...items[0], id }) as IconItem,
    ),
    loadFullList: vi.fn<() => Promise<AgnosticOption[]>>(async () => []),
  }
  const store = base as unknown as EntityStore<IconItem>
  return store
}

function makeCategoryStore(): EntityStore {
  const base = {
    $id: 'entity:Category',
    $state: undefined as never,
    $patch: vi.fn<(partial: unknown) => void>(),
    $reset: vi.fn<() => void>(),
    $dispose: vi.fn<() => void>(),
    name: 'Category',
    columns: [],
    items: [],
    pagination: {
      itemsPerPage: 10,
      currentPage: 1,
      totalCount: 0,
      lastPage: 1,
      hasNextPage: false,
    },
    filters: {},
    order: [],
    item: null,
    fullList: [{ id: '/api/categories/1', label: 'Navegación' }],
    loadColumns: vi.fn<() => Promise<CollectionFieldConfig[]>>(async () => []),
    fetchItems: vi.fn<() => Promise<unknown[]>>(async () => []),
    fetchItem: vi.fn<(id: string | number) => Promise<unknown>>(async () => ({})),
    create: vi.fn<(data: Record<string, unknown>) => Promise<unknown>>(async () => ({})),
    update: vi.fn<(data: Record<string, unknown>) => Promise<unknown>>(async () => ({})),
    remove: vi.fn<(id: string | number) => Promise<unknown>>(async () => ({})),
    loadFullList: vi.fn<() => Promise<AgnosticOption[]>>(async () => [
      { id: '/api/categories/1', label: 'Navegación' },
    ]),
  }
  return base as unknown as EntityStore
}

const pluginMount = (): ComponentMountingOptions<typeof List> => ({
  global: {
    plugins: [PrimeVue, [formkitPlugin, defaultConfig(formkitConfig())]],
  },
})

describe('List.vue', () => {
  let store: EntityStore<IconItem>
  let categoryStore: EntityStore
  let wrapper: VueWrapper | null = null

  beforeEach(() => {
    store = makeStore()
    categoryStore = makeCategoryStore()
    schemaRepoMock.getEntity.mockReset()
    registryMock.getEntity.mockReset()
    registryMock.getEntity.mockImplementation(
      (name: string): EntityStore => (name === 'Category' ? categoryStore : (store as EntityStore)),
    )
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    vi.useRealTimers()
  })

  it('muestra error si la entidad no existe', async () => {
    schemaRepoMock.getEntity.mockReturnValue(null)
    wrapper = mount(List, { props: { entity: 'Nope' }, ...pluginMount() })
    await flushPromises()
    expect(wrapper.text()).toContain('no encontrada')
  })

  it('renderiza título, filas, acciones y precarga relaciones', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    expect(store.loadColumns).toHaveBeenCalled()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Icon')
    expect(wrapper.text()).toContain('home')
    expect(wrapper.text()).toContain('Navegación')
    expect(wrapper.findAll('.pi-pencil')).toHaveLength(1)
    expect(wrapper.findAll('.pi-trash')).toHaveLength(1)
    expect(registryMock.getEntity).toHaveBeenCalledWith('Category')
    expect(categoryStore.loadFullList).toHaveBeenCalled()
    expect(wrapper.find('.p-paginator').exists()).toBe(false)
  })

  it('filtro de texto con debounce de 500ms aplica al store y refetcha', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    const input = wrapper.find('input[name="filter_name"]')
    expect(input.exists()).toBe(true)
    await input.setValue('ho')
    await flushPromises()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)

    await new Promise((resolve) => setTimeout(resolve, 650))
    expect(store.filters).toEqual({ name: 'ho' })
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('filtro sin arg de servidor avisa "filtro local" y no puebla filtros del servidor', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    await wrapper.find('input[name="filter_description"]').setValue('Ini')
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(wrapper.text()).toContain('Filtro local')

    await new Promise((resolve) => setTimeout(resolve, 650))
    expect(store.filters).toEqual({})
  })

  it('confirma y elimina el registro seleccionado', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    await wrapper.findAll('.pi-trash')[0]?.trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('Confirmar eliminación')

    const confirmEl = Array.from(document.body.querySelectorAll('button')).find(
      (button) => button.textContent === 'Eliminar',
    )
    expect(confirmEl).toBeTruthy()
    await new DOMWrapper(confirmEl!).trigger('click')
    await flushPromises()

    expect(store.remove).toHaveBeenCalledWith('/api/icons/1')
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
    expect(document.body.textContent).not.toContain('Confirmar eliminación')
  })

  it('muestra el id como número, no como IRI del resource', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    expect(wrapper.text()).not.toContain('/api/icons/1')
    expect(wrapper.find('tbody tr td').text()).toBe('1')
  })

  it('limpia el filtro de texto con el icono, sin esperar el debounce', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    const input = wrapper.find('input[name="filter_name"]')
    await input.setValue('ho')
    await flushPromises()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)

    const clear = wrapper.find('.pi-times')
    expect(clear.exists()).toBe(true)
    await clear.trigger('click')
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 20))

    expect(store.filters).toEqual({})
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('oculta columnas y las restaura desde el indicador', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    const hideIcon = wrapper.find('[aria-label="Ocultar columna Icono"]')
    expect(hideIcon.exists()).toBe(true)
    await hideIcon.trigger('click')
    await flushPromises()

    expect(store.columns.find((col) => col.field === 'icon')?.visible).toBe(false)
    expect(wrapper.find('[aria-label="Ocultar columna Icono"]').exists()).toBe(false)

    const indicator = wrapper.find('[aria-label="1 columnas ocultas"]')
    expect(indicator.exists()).toBe(true)
    await indicator.trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('Columnas ocultas')

    const restoreBtn = Array.from(document.body.querySelectorAll('button')).find(
      (button) => button.textContent?.trim() === 'Icono',
    )
    expect(restoreBtn).toBeTruthy()
    await new DOMWrapper(restoreBtn!).trigger('click')
    await flushPromises()

    expect(store.columns.find((col) => col.field === 'icon')?.visible).toBe(true)
    expect(wrapper.find('[aria-label="1 columnas ocultas"]').exists()).toBe(false)
  })

  it('reordena columnas y sincroniza el array del store', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()

    const dataTable = wrapper.findComponent({ name: 'DataTable' })
    dataTable.vm.$emit('column-reorder', { originalEvent: {}, dragIndex: 0, dropIndex: 2 })
    await flushPromises()

    expect(store.columns.map((col) => col.field)).toEqual([
      'name',
      'icon',
      'id',
      'description',
      'category',
    ])
  })

  it('hidrata los filtros persistidos del store', async () => {
    schemaRepoMock.getEntity.mockReturnValue(iconSchema)
    store.filters = { name: 'ho' }
    wrapper = mount(List, { props: { entity: 'Icon' }, ...pluginMount() })
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 20))

    const input = wrapper.find('input[name="filter_name"]')
    expect((input.element as HTMLInputElement).value).toBe('ho')
  })
})
