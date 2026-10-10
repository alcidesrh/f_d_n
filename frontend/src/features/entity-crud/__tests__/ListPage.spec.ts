import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createPinia, getActivePinia, setActivePinia } from 'pinia'
import {
  DOMWrapper,
  flushPromises,
  mount,
  type ComponentMountingOptions,
  type VueWrapper,
} from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ConfirmDialog from 'primevue/confirmdialog'
import { reactive } from 'vue'
import formkitConfig from '@/shared/formkit/config'
import { dismissAll } from '@/core/notify'
import type { AgnosticOption, EntitySchema } from '@/core/graphql/types'
import type { CollectionFieldConfig, EntityStore } from '@/core/entities/types'
import List from '@/features/entity-crud/ListPage.vue'
import Toasts from '@/app/layout/Toasts.vue'
import DataGrid from '@/shared/data-grid/DataGrid.vue'
import ListCellEditor from '@/features/entity-crud/list/ListCellEditor.vue'
import ListDateRangeFilter from '@/features/entity-crud/list/ListDateRangeFilter.vue'

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

const { schemaMock, registryMock } = vi.hoisted(() => ({
  schemaMock: { find: vi.fn<(name: string) => EntitySchema | null>() },
  registryMock: { getEntity: vi.fn<(name: string) => EntityStore>() },
}))

vi.mock('@/core/entities/schema', () => ({ useSchemaStore: () => schemaMock }))
vi.mock('@/core/entities/registry', () => ({ getEntity: registryMock.getEntity }))

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
    enumValues: [],
  }
}

function arg(name: string) {
  return { name, type: 'String', namedType: 'String', required: false, isList: false }
}

const iconSchema: EntitySchema = {
  name: 'Icon',
  slug: null,
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

const deletableSchema: EntitySchema = {
  ...iconSchema,
  delete: {
    kind: 'delete',
    field: 'deleteIcon',
    inputType: 'deleteIconInput',
    payloadType: 'deleteIconPayload',
    returnsField: 'icon',
    inputFields: [],
  },
}

const editableSchema: EntitySchema = {
  ...iconSchema,
  scalarFields: [...iconSchema.scalarFields, 'createdAt'],
  fields: [...iconSchema.fields, field('createdAt', 'DateType')],
  update: {
    kind: 'update',
    field: 'updateIcon',
    inputType: 'IconUpdateInput',
    payloadType: 'IconPayload',
    returnsField: 'icon',
    inputFields: [
      {
        name: 'name',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'icon',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'createdAt',
        type: 'DateType',
        namedType: 'DateType',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'category',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
}

function makeStore(): EntityStore<IconItem> {
  const base = {
    $id: 'entity:Icon',
    $patch: vi.fn<(partial: unknown) => void>(),
    $reset: vi.fn<() => void>(),
    name: 'Icon',
    columns: [],
    items: [...items],
    get metadata(): EntitySchema | null {
      return schemaMock.find(this.name)
    },
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
    formFields: [],
    listOptions: {},
    view: { density: 'normal', layout: 'auto', filterMode: 'or', filtersOpen: false },
    init: vi.fn<(force?: boolean) => Promise<void>>(async (force = false) => {
      if (force || store.columns.length === 0) store.columns = columns.map((col) => ({ ...col }))
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
  const store = reactive(base) as unknown as EntityStore<IconItem>
  return store
}

function makeCategoryStore(): EntityStore {
  const base = {
    $id: 'entity:Category',
    $patch: vi.fn<(partial: unknown) => void>(),
    $reset: vi.fn<() => void>(),
    name: 'Category',
    columns: [],
    items: [],
    get metadata(): EntitySchema | null {
      return schemaMock.find(this.name)
    },
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
    formFields: [],
    listOptions: {},
    view: { density: 'normal', layout: 'auto', filterMode: 'or', filtersOpen: false },
    init: vi.fn<() => Promise<void>>(async () => {}),
    fetchItems: vi.fn<() => Promise<unknown[]>>(async () => []),
    fetchItem: vi.fn<(id: string | number) => Promise<unknown>>(async () => ({})),
    create: vi.fn<(data: Record<string, unknown>) => Promise<unknown>>(async () => ({})),
    update: vi.fn<(data: Record<string, unknown>) => Promise<unknown>>(async () => ({})),
    remove: vi.fn<(id: string | number) => Promise<unknown>>(async () => ({})),
    loadFullList: vi.fn<() => Promise<AgnosticOption[]>>(async () => [
      { id: '/api/categories/1', label: 'Navegación' },
    ]),
  }
  return reactive(base) as unknown as EntityStore
}

/** Las notificaciones se pintan un macrotask después (ver core/notify). */
async function settleToasts() {
  await new Promise((resolve) => setTimeout(resolve, 0))
  await flushPromises()
}

const pluginMount = (): ComponentMountingOptions<typeof List> => ({
  global: {
    plugins: [
      getActivePinia()!,
      PrimeVue,
      ConfirmationService,
      [formkitPlugin, defaultConfig(formkitConfig())],
    ],
  },
})

describe('ListPage', () => {
  let store: EntityStore<IconItem>
  let categoryStore: EntityStore
  let wrapper: VueWrapper | null = null
  let toastsWrapper: VueWrapper | null = null
  let confirmWrapper: VueWrapper | null = null

  beforeEach(() => {
    setActivePinia(createPinia())
    store = makeStore()
    categoryStore = makeCategoryStore()
    schemaMock.find.mockReset()
    registryMock.getEntity.mockReset()
    registryMock.getEntity.mockImplementation(
      (name: string): EntityStore => (name === 'Category' ? categoryStore : (store as EntityStore)),
    )
    toastsWrapper = mount(Toasts, { global: { plugins: [PrimeVue] } })
    confirmWrapper = mount(ConfirmDialog, {
      attachTo: document.body,
      global: { plugins: [PrimeVue, ConfirmationService] },
    })
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    toastsWrapper?.unmount()
    toastsWrapper = null
    confirmWrapper?.unmount()
    confirmWrapper = null
    vi.useRealTimers()
    dismissAll()
  })

  async function mountList(schema: EntitySchema, props: Record<string, unknown> = {}) {
    schemaMock.find.mockImplementation((name: string) => (name === 'Category' ? null : schema))
    wrapper = mount(List, { props: { entity: 'Icon', ...props }, attachTo: document.body, ...pluginMount() })
    await flushPromises()
    return wrapper
  }

  /** Activa el filtro de una columna y devuelve su input. */
  async function filterInput(field: string) {
    const column = store.columns.find((c) => c.field === field)
    if (column) column.showFilter = true
    await flushPromises()
    return wrapper!.find(`[data-grid-filter="${field}"] input`)
  }

  const bodyButton = (text: string) =>
    Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.trim() === text)

  const dataCells = () => wrapper!.findAll('.dg-brow .dg-dcell')

  it('muestra error si la entidad no existe', async () => {
    schemaMock.find.mockReturnValue(null)
    wrapper = mount(List, { props: { entity: 'Nope' }, ...pluginMount() })
    await settleToasts()
    expect(document.body.textContent).toContain('no encontrada')
  })

  it('renderiza cabeceras, filas, acciones y precarga relaciones', async () => {
    // Entidad no paginada (collectionKind "list"): su store no lleva `pagination`.
    delete (store as { pagination?: unknown }).pagination
    // `category` con filtro en el backend: su select necesita las opciones.
    await mountList({ ...iconSchema, filterArgs: [...iconSchema.filterArgs, arg('category')] })

    expect(store.init).toHaveBeenCalled()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)
    expect(wrapper!.findAll('[role="columnheader"][data-grid-col]').map((h) => h.attributes('data-grid-col'))).toEqual([
      'id',
      'name',
      'icon',
      'description',
      'category',
    ])
    expect(wrapper!.text()).toContain('home')
    expect(wrapper!.text()).toContain('Navegación')
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(1)
    // Sin mutación de delete no se ofrece eliminar.
    expect(wrapper!.findAll('[aria-label="Eliminar"]')).toHaveLength(0)
    expect(registryMock.getEntity).toHaveBeenCalledWith('Category')
    expect(categoryStore.loadFullList).toHaveBeenCalled()
    expect(wrapper!.find('.p-paginator').exists()).toBe(false)
  })

  it('las celdas de relación muestran label, nombre, name o id en ese orden', async () => {
    store.items = [{ ...items[0]!, category: { id: '/api/categories/9', nombre: 'Rutas', name: 'routes' } as never }]
    await mountList(iconSchema)
    expect(dataCells()[4]!.text()).toBe('Rutas')
  })

  it('filtro de texto con debounce aplica al store y refetcha', async () => {
    await mountList(iconSchema)
    const input = await filterInput('name')
    expect(input.exists()).toBe(true)
    await input.setValue('ho')
    await flushPromises()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)

    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(store.filters).toEqual({ name: 'ho' })
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('una columna sin arg de servidor no ofrece filtro', async () => {
    await mountList(iconSchema)
    expect((await filterInput('name')).exists()).toBe(true)
    expect((await filterInput('description')).exists()).toBe(false)
    expect(wrapper!.find('[aria-label="Filtrar Descripción"]').exists()).toBe(false)
    expect(wrapper!.find('[aria-label="Filtrar Nombre"]').exists()).toBe(true)
  })

  it('el embudo activa solo el filtro de su columna; activado, lo limpia y lo oculta', async () => {
    await mountList({ ...iconSchema, filterArgs: [...iconSchema.filterArgs, arg('_combinar')] })
    expect(wrapper!.find('.dg-filters').exists()).toBe(false)

    await wrapper!.find('button[aria-label="Filtrar Nombre"]').trigger('click')
    await flushPromises()
    expect(wrapper!.find('[data-grid-filter="name"] input').exists()).toBe(true)
    expect(wrapper!.find('[data-grid-filter="icon"] input').exists()).toBe(false)
    expect(wrapper!.find('[data-grid-col="name"] button[aria-label="Quitar filtro Nombre"]').attributes('aria-pressed')).toBe('true')

    await wrapper!.find('[data-grid-filter="name"] input').setValue('ho')
    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(store.filters).toEqual({ name: 'ho' })

    await wrapper!.find('[data-grid-col="name"] button[aria-label="Quitar filtro Nombre"]').trigger('click')
    await flushPromises()
    expect(store.filters).toEqual({})
    expect(wrapper!.find('.dg-filters').exists()).toBe(false)
    expect(wrapper!.find('button[aria-label="Filtrar Nombre"]').exists()).toBe(true)
  })

  it('sin filterable: true explícito no se ofrece filtro', async () => {
    store.init = vi.fn<() => Promise<void>>(async () => {
      store.columns = columns.map((col) => ({ ...col, filterable: col.field === 'icon' ? true : null }))
    }) as never
    await mountList(iconSchema)
    expect(wrapper!.find('[aria-label="Filtrar Nombre"]').exists()).toBe(false)
    expect(wrapper!.find('[aria-label="Filtrar Icono"]').exists()).toBe(true)
  })

  it('el rango de fechas se aplica con Buscar (con hora) y no antes', async () => {
    const withDate: EntitySchema = {
      ...editableSchema,
      filterArgs: [...iconSchema.filterArgs, arg('createdAt_after'), arg('createdAt_before')],
      fields: [...iconSchema.fields, field('createdAt', 'Date')],
    }
    store.init = vi.fn<() => Promise<void>>(async () => {
      store.columns = [...columns.map((col) => ({ ...col })), { field: 'createdAt', label: 'Creado', filterable: true }]
    }) as never
    await mountList(withDate)
    await wrapper!.find('button[aria-label="Filtrar Creado"]').trigger('click')
    await flushPromises()

    const range = wrapper!.findComponent(ListDateRangeFilter)
    const vm = range.vm as unknown as { open: (e: Event) => void; search: () => void; days: Date[]; fromTime: Date; toTime: Date }
    vm.open(new MouseEvent('click'))
    vm.days = [new Date(2026, 9, 1), new Date(2026, 9, 5)]
    vm.fromTime = new Date(2000, 0, 1, 8, 30)
    await flushPromises()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)

    vm.search()
    await flushPromises()
    expect(store.filters).toEqual({ createdAt_after: '2026-10-01T08:30', createdAt_before: '2026-10-05T23:59' })
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
    expect(wrapper!.text()).toContain('01/10/2026 08:30 – 05/10/2026 23:59')
  })

  it('varios filtros se combinan con OR por defecto y con AND al elegir "Todos"', async () => {
    store.filters = { name: 'ho', icon: 'pi' }
    await mountList({ ...iconSchema, filterArgs: [...iconSchema.filterArgs, arg('_combinar')] })

    // Los filtros hidratados aparecen como chips con el selector de combinación.
    expect(wrapper!.text()).toContain('Nombre:')
    expect(wrapper!.text()).toContain('Cualquiera')
    const input = await filterInput('name')
    await input.setValue('hom')
    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(store.filters).toEqual({ name: 'hom', icon: 'pi', _combinar: 'or' })

    const todos = wrapper!.findAll('[role="radio"], button').find((b) => b.text() === 'Todos')
    await todos?.trigger('click')
    await flushPromises()
    expect(store.view.filterMode).toBe('and')
    expect(store.filters).toEqual({ name: 'hom', icon: 'pi' })
  })

  it('confirma y elimina un registro desde su fila', async () => {
    await mountList(deletableSchema)

    await wrapper!.findAll('[aria-label="Eliminar"]')[0]?.trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('Confirmar eliminación')

    await new DOMWrapper(bodyButton('Eliminar')!).trigger('click')
    await flushPromises()
    await settleToasts()
    expect(store.remove).toHaveBeenCalledWith('/api/icons/1')
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
    expect(document.body.textContent).toContain('Registro eliminado')
  })

  it('muestra el id como número, no como IRI del resource', async () => {
    await mountList(iconSchema)
    expect(wrapper!.text()).not.toContain('/api/icons/1')
    expect(dataCells()[0]!.text()).toBe('1')
  })

  it('limpia el filtro de texto con el icono, sin esperar el debounce', async () => {
    await mountList(iconSchema)
    const input = await filterInput('name')
    await input.setValue('ho')
    await flushPromises()
    expect(store.fetchItems).toHaveBeenCalledTimes(1)

    await wrapper!.find('[aria-label="Limpiar filtro Nombre"]').trigger('click')
    await flushPromises()
    expect(store.filters).toEqual({})
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('oculta columnas desde la cabecera y las restaura desde el menú de columnas', async () => {
    await mountList(iconSchema)
    await wrapper!.find('[aria-label="Ocultar columna Icono"]').trigger('click')
    await flushPromises()

    expect(store.columns.find((col) => col.field === 'icon')?.visible).toBe(false)
    expect(wrapper!.find('[data-grid-col="icon"]').exists()).toBe(false)

    await wrapper!.find('[aria-label="Columnas"]').trigger('click')
    await flushPromises()
    const label = Array.from(document.body.querySelectorAll('label')).find((l) => l.textContent?.trim() === 'Icono')
    expect(label).toBeTruthy()
    await new DOMWrapper(label!.querySelector('input')!).trigger('click')
    await flushPromises()

    expect(store.columns.find((col) => col.field === 'icon')?.visible).toBe(true)
    // La columna que aparece necesita sus datos.
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('reordena columnas y sincroniza el array del store (las ocultas quedan en su sitio)', async () => {
    await mountList(iconSchema)
    store.columns.find((c) => c.field === 'description')!.visible = false
    await flushPromises()

    wrapper!.findComponent(DataGrid).vm.$emit('reorder', 0, 2)
    await flushPromises()
    expect(store.columns.map((col) => col.field)).toEqual(['name', 'icon', 'id', 'description', 'category'])
  })

  it('cambia el ancho de una columna y el doble clic vuelve al de la configuración', async () => {
    await mountList(iconSchema)
    const grid = wrapper!.findComponent(DataGrid)
    grid.vm.$emit('resize', 'name', '240px')
    await flushPromises()
    expect(store.columns.find((c) => c.field === 'name')?.width).toBe('240px')
    expect((wrapper!.find('.dg').element as HTMLElement).style.getPropertyValue('--dg-cols')).toContain('240px')

    grid.vm.$emit('resize', 'name', null)
    await flushPromises()
    expect(store.columns.find((c) => c.field === 'name')?.width ?? null).toBeNull()
  })

  it('hidrata los filtros persistidos del store', async () => {
    store.filters = { name: 'ho' }
    await mountList(iconSchema)
    const input = await filterInput('name')
    expect((input.element as HTMLInputElement).value).toBe('ho')
  })

  it('edita una celda en línea y persiste solo el campo editado', async () => {
    await mountList(editableSchema)

    const nameCell = dataCells()[1]!
    expect(nameCell.classes()).toContain('is-editable')
    await nameCell.trigger('click')
    await flushPromises()
    const input = nameCell.find('input')
    expect(input.exists()).toBe(true)
    await input.setValue('dashboard')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(store.update).toHaveBeenCalledWith({ id: '/api/icons/1', name: 'dashboard' })
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
    await settleToasts()
    expect(document.body.textContent).toContain('Cambio guardado')
  })

  it('Escape cancela la edición y no guarda nada', async () => {
    await mountList(editableSchema)
    const nameCell = dataCells()[1]!
    await nameCell.trigger('click')
    await flushPromises()
    await nameCell.find('input').setValue('otro')
    await nameCell.find('input').trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(store.update).not.toHaveBeenCalled()
    expect(wrapper!.findComponent(ListCellEditor).exists()).toBe(false)
  })

  it('normaliza fechas a YYYY-MM-DD al editar una celda', async () => {
    await mountList(editableSchema)
    store.columns = [...store.columns, { field: 'createdAt', label: 'Creado', filterable: false }]
    store.items = [{ ...items[0]!, createdAt: '2014-02-27T11:54:20-06:00' } as never]
    await flushPromises()

    await dataCells()[5]!.trigger('click')
    await flushPromises()
    const editor = wrapper!.findComponent(ListCellEditor)
    ;(editor.props('data') as Record<string, unknown>).createdAt = new Date(2014, 2, 3)
    editor.vm.$emit('commit')
    await flushPromises()

    expect(store.update).toHaveBeenCalledWith({ id: '/api/icons/1', createdAt: '2014-03-03' })
  })

  it('modo selección: oculta acciones, marca filas al tocarlas y muestra el contador', async () => {
    await mountList(iconSchema)
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(1)

    await wrapper!.find('[aria-label="Modo selección"]').trigger('click')
    await flushPromises()
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(0)
    expect(wrapper!.find('[aria-label="Seleccionar la página"]').exists()).toBe(true)

    await wrapper!.find('.dg-brow').trigger('click')
    await flushPromises()
    expect(wrapper!.text()).toContain('1 seleccionado')
    expect(wrapper!.find('.dg-brow').classes()).toContain('is-selected')
  })

  it('elimina los seleccionados (acción por defecto de la selección)', async () => {
    store.items = [...items, { ...items[0]!, id: '/api/icons/2', name: 'menu' }]
    await mountList(deletableSchema)
    await wrapper!.find('[aria-label="Modo selección"]').trigger('click')
    wrapper!.findComponent(DataGrid).vm.$emit('toggle-page', true)
    await flushPromises()
    expect(wrapper!.text()).toContain('2 seleccionados')

    await wrapper!.find('[aria-label="Eliminar"]').trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('Eliminar seleccionados')
    const confirmButtons = Array.from(document.body.querySelectorAll('.p-confirmdialog button'))
    await new DOMWrapper(confirmButtons.find((b) => b.textContent?.trim() === 'Eliminar')!).trigger('click')
    await flushPromises()
    await settleToasts()

    expect(store.remove).toHaveBeenCalledWith('/api/icons/1')
    expect(store.remove).toHaveBeenCalledWith('/api/icons/2')
    expect(document.body.textContent).toContain('2 registros eliminados')
  })

  it('como selector: siempre en selección, sin acciones, y emite la selección', async () => {
    await mountList(iconSchema, { seleccion: [] })
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(0)
    expect(wrapper!.find('[aria-label="Modo selección"]').exists()).toBe(true)
    await wrapper!.find('.dg-brow').trigger('click')
    expect(wrapper!.emitted('update:seleccion')?.[0]?.[0]).toEqual([items[0]])
  })

  it('features apaga partes del listado en una vista', async () => {
    await mountList(iconSchema, { features: { toolbar: false, rowActions: false, filter: false } })
    expect(wrapper!.find('[aria-label="Controles del listado"]').exists()).toBe(false)
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(0)
    expect(wrapper!.find('[aria-label="Filtrar Nombre"]').exists()).toBe(false)
  })

  it('maximiza sobre toda la pantalla y Escape lo restaura', async () => {
    await mountList(iconSchema)
    await wrapper!.find('[aria-label="Maximizar"]').trigger('click')
    await flushPromises()
    expect(document.body.querySelector('.list-page.is-maximized')).toBeTruthy()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(document.body.querySelector('.list-page.is-maximized')).toBeFalsy()
  })

  it('restablecer la vista limpia filtros, orden, página, vista, ocultas y selección', async () => {
    store.filters = { name: 'ho' }
    store.order = [{ name: 'ASC' }]
    store.pagination!.currentPage = 3
    store.pagination!.itemsPerPage = 25
    await mountList(iconSchema)
    store.view.density = 'compact'
    store.view.filtersOpen = true

    store.columns.find((col) => col.field === 'icon')!.visible = false
    await wrapper!.find('[aria-label="Modo selección"]').trigger('click')
    await flushPromises()

    await wrapper!.find('[aria-label="Restablecer vista"]').trigger('click')
    await flushPromises()

    expect(store.init).toHaveBeenLastCalledWith(true)
    expect(store.filters).toEqual({})
    expect(store.order).toEqual([])
    expect(store.pagination?.currentPage).toBe(1)
    expect(store.pagination?.itemsPerPage).toBe(10)
    expect(store.view).toEqual({ density: 'normal', layout: 'auto', filterMode: 'or', filtersOpen: false })
    expect(store.columns.find((col) => col.field === 'icon')?.visible).not.toBe(false)
    expect(wrapper!.findAll('[aria-label="Editar"]')).toHaveLength(1)
  })

  it('resalta coincidencias solo tras renderizar el resultado del fetch', async () => {
    await mountList(iconSchema)
    expect(wrapper!.find('mark').exists()).toBe(false)

    store.filters = { name: 'ho' }
    store.items = [...items]
    await flushPromises()
    expect(wrapper!.find('mark').text()).toBe('ho')
  })

  it('no resalta mientras el filtro no se haya aplicado al store', async () => {
    await mountList(iconSchema)
    await (await filterInput('name')).setValue('ho')
    await new Promise((resolve) => setTimeout(resolve, 20))
    await flushPromises()
    expect(wrapper!.find('mark').exists()).toBe(false)
  })

  it('pinta el orden en la cabecera según el estado del store', async () => {
    store.order = [{ name: 'ASC' }]
    store.init = vi.fn<() => Promise<void>>(async () => {
      store.columns = columns.map((col) => ({ ...col, sortable: true }))
    }) as never
    await mountList({ ...iconSchema, orderInput: 'IconFilter_order', orderFields: ['name'] })

    const header = () => wrapper!.find('[data-grid-col="name"]')
    expect(header().attributes('aria-sort')).toBe('ascending')

    await wrapper!.find('[aria-label="Ordenar por Nombre"]').trigger('click')
    await flushPromises()
    expect(store.order).toEqual([{ name: 'DESC' }])
    expect(header().attributes('aria-sort')).toBe('descending')
    expect(store.fetchItems).toHaveBeenCalledTimes(2)
  })

  it('no permite ordenar columnas fuera del input de orden del backend', async () => {
    store.init = vi.fn<() => Promise<void>>(async () => {
      store.columns = columns.map((col) => ({ ...col, sortable: true }))
    }) as never
    await mountList({ ...iconSchema, orderInput: 'IconFilter_order', orderFields: ['name'] })
    expect(wrapper!.find('[aria-label="Ordenar por Nombre"]').exists()).toBe(true)
    expect(wrapper!.find('[aria-label="Ordenar por Icono"]').exists()).toBe(false)
  })

  it('una columna con sortable/filterable en false no ordena ni filtra', async () => {
    store.init = vi.fn<() => Promise<void>>(async () => {
      store.columns = columns.map((col) => ({ ...col, sortable: false, filterable: col.field === 'name' ? false : col.filterable }))
    }) as never
    await mountList({ ...iconSchema, orderInput: 'IconFilter_order', orderFields: ['name'] })
    expect(wrapper!.find('[aria-label="Ordenar por Nombre"]').exists()).toBe(false)
    expect(wrapper!.find('[aria-label="Filtrar Nombre"]').exists()).toBe(false)
  })
})
