import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { Porcion } from '@/core/tablero/porciones'
import DonaPorciones from '../DonaPorciones.vue'

const p = (clave: string, valor: number): Porcion => ({ clave, etiqueta: clave.toUpperCase(), nombre: `Estación ${clave}`, valor })
// 5 grandes y una cola de 6 que va a "Otros".
const datos = (extra = 0) => [...['a', 'b', 'c', 'd', 'e'].map((c, i) => p(c, 200 - i * 10)), ...['f', 'g', 'h', 'i', 'j', 'k'].map((c, i) => p(c, 10 - i + (c === 'f' ? extra : 0)))]

function montar() {
  return mount(DonaPorciones, {
    props: { porciones: datos(), formato: (v: number) => String(v), totalRotulo: 'total', agrupa: true, nombreResto: 'puntos de venta', titulo: 'Prueba' },
    global: { stubs: { icon: true, Button: { template: '<button><slot name="icon" /></button>' } } },
  })
}

describe('DonaPorciones', () => {
  it('abrir "Otros" reparte la cola en otro nivel, con el camino de vuelta', async () => {
    const w = montar()
    expect(w.find('.otros-boton').text()).toContain('Otros · 6 puntos de venta')
    await w.find('.otros-boton').trigger('click')
    expect(w.find('nav.camino').text()).toContain('Otros')
    expect(w.find('.centro').text()).toContain('6 puntos de venta')
  })

  it('cifras nuevas con el mismo rango: rehace los niveles sin cerrar el abierto', async () => {
    const w = montar()
    await w.find('.otros-boton').trigger('click')
    await w.setProps({ porciones: datos(5) })
    expect(w.find('nav.camino').exists()).toBe(true)
    // La cola sube de 45 a 50: el centro del nivel abierto lo dice.
    expect(w.find('.centro').text()).toContain('50')
  })
})
