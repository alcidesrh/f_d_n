import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import SalidasLista from '@/features/venta/SalidasLista.vue'
import type { SalidaResumen } from '@/core/venta/types'

const salida = (id: number, extra: Partial<SalidaResumen> = {}): SalidaResumen => ({
  id,
  salida: '2026-10-10T08:00:00-06:00',
  salidaEstacion: null,
  estado: 'programada',
  empresa: { id: 1, nombre: 'Fuente del Norte' },
  bus: { id: 7, codigo: 'B-07', gama: 'Oro' },
  trayecto: { id: 3, origen: { id: 1, nombre: 'Guatemala' }, destino: { id: 2, nombre: 'Flores' } },
  vendidos: 12,
  capacidad: 40,
  precios: [
    { clase: 'A', precio: { centavos: 10000, moneda: 'GTQ', texto: 'Q 100.00' } },
    { clase: 'B', precio: { centavos: 12550, moneda: 'GTQ', texto: 'Q 125.50' } },
  ],
  ...extra,
})

const montar = (salidas: SalidaResumen[], salidaId: number | null = null) =>
  mount(SalidasLista, { props: { salidas, cargando: false, salidaId }, global: { plugins: [PrimeVue] }, attachTo: document.body })

describe('SalidasLista', () => {
  it('pinta una fila por salida con trayecto, bus, precios y asientos', () => {
    const w = montar([salida(1), salida(2, { bus: null, vendidos: null })])
    const filas = w.findAll('.dg-brow')
    expect(filas).toHaveLength(2)
    expect(filas[0]!.text()).toContain('Guatemala → Flores')
    expect(filas[0]!.find('.precios').text()).toBe('A Q 100B Q 125.50')
    expect(filas[0]!.find('.asientos__total').exists()).toBe(false)
    expect(filas[0]!.text()).toContain('B-07')
    expect(filas[0]!.find('.asientos__libres').text()).toBe('28')
    expect(filas[1]!.find('.asientos__libres').text()).toBe('40')
    w.unmount()
  })

  it('emite elegir con clic y con Enter, y resalta la elegida', async () => {
    const w = montar([salida(1), salida(2)], 2)
    const filas = w.findAll('.dg-brow')
    expect(filas[1]!.classes()).toContain('is-selected')
    expect(filas[0]!.classes()).not.toContain('is-selected')
    await filas[0]!.trigger('click')
    await filas[1]!.trigger('keydown', { key: 'Enter' })
    expect(w.emitted('elegir')).toEqual([[1], [2]])
    w.unmount()
  })

  it('marca las canceladas y las que no se venden', () => {
    const w = montar([salida(1, { estado: 'cancelada' }), salida(2, { bus: null }), salida(3)])
    const [cancelada, sinBus, normal] = w.findAll('.dg-brow')
    expect(cancelada!.classes()).toEqual(expect.arrayContaining(['is-cancelada', 'is-no-vendible']))
    expect(sinBus!.classes()).toContain('is-no-vendible')
    expect(normal!.classes()).not.toContain('is-no-vendible')
    w.unmount()
  })

  it('sin salidas muestra el aviso', () => {
    const w = montar([])
    expect(w.text()).toContain('No hay salidas para esa fecha y estación.')
    w.unmount()
  })
})
