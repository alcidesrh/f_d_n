import { describe, expect, it } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import type { AsientoCroquis, ElementoCroquis } from '@/core/croquis/types'
import { plantasClaseBPrimero } from '../croquis'
import TarjetaBus from '../TarjetaBus.vue'
import TarjetaSalida from '../TarjetaSalida.vue'
import TarjetaBoleto from '../TarjetaBoleto.vue'
import Tarjeta from '../Tarjeta.vue'

const asiento = (numero: number, planta: number, clase: 'A' | 'B'): AsientoCroquis => ({ tipo: 'asiento', id: numero, numero, clase, planta, fila: numero, columna: 1 })

globalThis.ResizeObserver ??= class {
  observe() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

const global = { stubs: { RouterLink: RouterLinkStub, Tag: { props: ['value'], template: '<span>{{ value }}</span>' }, Icon: true }, directives: { tooltip: {} } }

describe('plantasClaseBPrimero', () => {
  it('pone delante la planta con clase B', () => {
    expect(plantasClaseBPrimero([asiento(1, 1, 'A'), asiento(2, 2, 'B')])).toEqual([2, 1])
    expect(plantasClaseBPrimero([asiento(1, 1, 'B'), asiento(2, 2, 'A')])).toEqual([1, 2])
    expect(plantasClaseBPrimero([asiento(1, 1, 'A')])).toEqual([1])
  })
})

describe('tarjetas del chat', () => {
  it('bus: código, empresa, asientos y croquis horizontal con la planta B primero', () => {
    const croquis: ElementoCroquis[] = [asiento(1, 2, 'A'), asiento(2, 1, 'B')]
    const w = mount(TarjetaBus, { props: { datos: { titulo: 'Bus 82', codigo: '82', empresa: 'PIONERA', clase: 'Clase Oro', asientos: 2, croquis } }, global })
    expect(w.text()).toContain('82')
    expect(w.text()).toContain('PIONERA')
    expect(w.text()).toContain('Asientos2')
    expect(w.find('.bm').classes()).toContain('bm--horizontal')
    expect(w.findAll('.bm-planta').map((p) => p.attributes('aria-label'))).toEqual(['Planta baja', 'Planta alta'])
  })

  it('salida: creada por, desglose de ocupados, sin cobrado y croquis vertical', () => {
    const datos = {
      titulo: 'Guatemala → Flores',
      id: 3,
      salida: '2026-10-10T08:00:00-06:00',
      estado: 'programada',
      empresa: { id: 1, nombre: 'FDN' },
      bus: { id: 2, codigo: '82', gama: null },
      trayecto: { id: 1, origen: { id: 1, nombre: 'Guatemala' }, destino: { id: 2, nombre: 'Flores' } },
      croquis: [asiento(1, 2, 'A'), asiento(2, 1, 'B')],
      ocupados: [{ asiento: 1, estado: 'vendido', canal: 'web', sinCobro: null }],
      pilotos: [],
      creadaPor: 'Ana López',
      creadaEn: '2026-10-01T09:00:00-06:00',
      resumen: { clases: [], asientos: 2, vendidos: 1, reservados: 0, canales: { estacion: 0, agencia: 0, web: 1, cortesia: 0, voucher: 0 }, boletosPorEstado: {}, ingresos: { texto: 'Q 100.00' } },
    }
    const w = mount(TarjetaSalida, { props: { datos }, global })
    expect(w.text()).toContain('FDN')
    expect(w.text()).toContain('por Ana López')
    expect(w.text()).toContain('1 ocupados')
    expect(w.text()).toContain('1 libres')
    expect(w.text()).toContain('Página 1')
    expect(w.text()).not.toContain('Cobrado')
    expect(w.find('.bm').classes()).toContain('bm--vertical')
    expect(w.findAll('.bm-planta').map((p) => p.attributes('aria-label'))).toEqual(['Planta baja', 'Planta alta'])
  })

  it('boleto: fecha y vendedor, trayecto, asiento, estado, salida y cliente', () => {
    const datos = { titulo: 'x', estado: 'emitido', asiento: 7, cliente: 'Sheril Castro', trayecto: { origen: 'Aguilar Batres', destino: 'San Pedro Sula' }, salida: { id: 4193, fecha: '2026-11-30T23:00:00-06:00' }, creado: '2026-09-07T08:18:48-06:00', vendedor: 'Cristian Cosio' }
    const w = mount(TarjetaBoleto, { props: { datos }, global })
    for (const t of ['Aguilar Batres → San Pedro Sula', 'Emitido', 'Asiento7', 'Sheril Castro', 'por Cristian Cosio']) expect(w.text()).toContain(t)
  })

  it('la cabecera muestra el id sin repetir el tipo ni su ícono', () => {
    const w = mount(Tarjeta, { props: { adjunto: { tipo: 'Bus', id: 2, estado: 'ok', datos: { titulo: 'Bus 82', codigo: '82', empresa: null, clase: null, asientos: 0, croquis: [] } } }, global })
    expect(w.find('.tarjeta__tipo').text()).toBe('ID 2')
    expect(w.find('.tarjeta__icono').exists()).toBe(false)
  })
})
