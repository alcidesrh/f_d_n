import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import { Draggable } from 'gsap/Draggable'
import DataGrid from '../DataGrid.vue'
import type { GridColumn } from '../types'

// Draggables falsos: en jsdom los reales son muy lentos (ver ListPage.spec) y
// aquí solo importa cuándo se crean y se destruyen.
const kill = vi.fn<() => void>()
const create = vi.spyOn(Draggable, 'create').mockImplementation(() => [{ kill } as unknown as Draggable])

const columnas = (...keys: string[]): GridColumn[] => keys.map((key) => ({ key, label: key, sortable: true }))

describe('useColumnDrag (DataGrid)', () => {
  let wrapper: VueWrapper | null = null

  beforeEach(() => {
    create.mockClear()
    kill.mockClear()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
  })

  async function montar(props: Record<string, unknown> = {}) {
    wrapper = mount(DataGrid, {
      props: { columns: columnas('a', 'b', 'c'), rows: [], rowKey: String, layout: 'table', reorderable: true, ...props },
      global: { plugins: [PrimeVue] },
    })
    await flushPromises()
    return wrapper
  }

  it('crea un draggable por cabecera al montar si es reordenable', async () => {
    await montar()
    expect(create).toHaveBeenCalledTimes(3)
  })

  it('sin `reorderable` no crea ninguno', async () => {
    await montar({ reorderable: false })
    expect(create).not.toHaveBeenCalled()
  })

  it('no los recrea si solo cambian otras propiedades de las columnas', async () => {
    const grid = await montar()
    create.mockClear()
    await grid.setProps({ columns: columnas('a', 'b', 'c').map((c) => ({ ...c, sort: c.key === 'b' ? 'asc' : null, filterable: true, width: '8rem' })) })
    await flushPromises()
    expect(create).not.toHaveBeenCalled()
    expect(kill).not.toHaveBeenCalled()
  })

  it('los recrea si cambia el orden de las columnas', async () => {
    const grid = await montar()
    create.mockClear()
    await grid.setProps({ columns: columnas('b', 'a', 'c') })
    await flushPromises()
    expect(kill).toHaveBeenCalledTimes(3)
    expect(create).toHaveBeenCalledTimes(3)
  })

  it('apagar y volver a encender `reorderable` los destruye y los recrea', async () => {
    const grid = await montar()
    create.mockClear()
    await grid.setProps({ reorderable: false })
    await flushPromises()
    expect(kill).toHaveBeenCalledTimes(3)
    expect(create).not.toHaveBeenCalled()
    await grid.setProps({ reorderable: true })
    await flushPromises()
    expect(create).toHaveBeenCalledTimes(3)
  })
})
