import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import type { AsientoCroquis, ElementoCroquis } from '@/core/croquis/types'
import BusMap from '../BusMap.vue'

const asiento = (numero: number, fila: number, columna: number, planta = 1): AsientoCroquis => ({
  tipo: 'asiento',
  id: numero,
  numero,
  clase: planta === 1 ? 'B' : 'A',
  planta,
  fila,
  columna,
})

const elementos: ElementoCroquis[] = [
  { tipo: 'chofer', id: 1, planta: 1, fila: 1, columna: 1 },
  { tipo: 'puerta', id: 2, planta: 1, fila: 1, columna: 5 },
  asiento(1, 2, 1),
  asiento(2, 2, 2),
  asiento(3, 1, 1, 2),
]

describe('BusMap', () => {
  it('pinta una carrocería por planta con sus elementos y nombra las plantas', () => {
    const wrapper = mount(BusMap, { props: { elementos } })
    const plantas = wrapper.findAll('.bm-planta')
    expect(plantas).toHaveLength(2)
    expect(plantas[0]!.text()).toContain('Planta baja')
    expect(plantas[1]!.text()).toContain('Planta alta')
    expect(wrapper.findAll('[data-celda]')).toHaveLength(5)
    expect(wrapper.find('[data-celda="1:2:1"]').attributes('aria-label')).toBe('Asiento 1, clase B')
  })

  it('con celdas vacías pinta toda la rejilla', () => {
    const wrapper = mount(BusMap, {
      props: {
        elementos,
        plantas: [1],
        rejilla: { 1: { filas: 3, columnas: 5 } },
        celdasVacias: true,
      },
    })
    expect(wrapper.findAll('[data-celda]')).toHaveLength(15)
    expect(wrapper.findAll('.bm-cell--vacia')).toHaveLength(11)
  })

  it('en horizontal el frente queda a la izquierda: la fila pasa a ser la columna CSS', () => {
    const wrapper = mount(BusMap, {
      props: { elementos, plantas: [1], orientacion: 'horizontal' },
    })
    const style = wrapper.find('[data-celda="1:2:1"]').attributes('style')
    expect(style).toContain('grid-column: 2')
    expect(style).toContain('grid-row: 5')
  })

  it('interactivo: los asientos son botones y los ocupados no se pueden elegir', async () => {
    const wrapper = mount(BusMap, {
      props: {
        elementos,
        interactivo: true,
        estado: (a: AsientoCroquis) =>
          a.numero === 2 ? 'ocupado' : a.numero === 1 ? 'seleccionado' : undefined,
      },
    })
    const libre = wrapper.find('[data-celda="2:1:1"]')
    const ocupado = wrapper.find('[data-celda="1:2:2"]')
    expect(libre.element.tagName).toBe('BUTTON')
    expect(ocupado.attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-celda="1:2:1"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-celda="1:1:1"]').element.tagName).toBe('DIV')

    await libre.trigger('click')
    await ocupado.trigger('click')
    expect(wrapper.emitted('asiento')).toEqual([[elementos[4]]])
  })

  it('el slot `celda` reemplaza el contenido de cada celda', () => {
    const wrapper = mount(BusMap, {
      props: { elementos: [asiento(7, 1, 1)] },
      slots: { celda: '<template #celda="{ elemento }">n{{ elemento.numero }}</template>' },
    })
    expect(wrapper.find('[data-celda="1:1:1"]').text()).toBe('n7')
  })
})
