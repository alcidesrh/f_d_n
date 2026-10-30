import { describe, expect, it } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import type { AsientoCroquis, ElementoCroquis } from '@/core/croquis/types'
import { plantasClaseBPrimero } from '../croquis'
import { tipoTarjeta } from '../catalogo'
import TarjetaBus from '../TarjetaBus.vue'
import TarjetaSalida from '../TarjetaSalida.vue'
import TarjetaBoleto from '../TarjetaBoleto.vue'
import TarjetaOcupacionSalida from '../TarjetaOcupacionSalida.vue'
import Tarjeta from '../Tarjeta.vue'

const asiento = (numero: number, planta: number, clase: 'A' | 'B'): AsientoCroquis => ({ tipo: 'asiento', id: numero, numero, clase, planta, fila: numero, columna: 1 })

globalThis.ResizeObserver ??= class {
  observe() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

const global = { stubs: { RouterLink: RouterLinkStub, Tag: { props: ['value'], template: '<span>{{ value }}</span>' }, Icon: true }, directives: { tooltip: {} } }

describe('tipoTarjeta', () => {
  it('la ocupación por vendedor va sin marco de grupo; el resto, con el suyo', () => {
    expect(tipoTarjeta('OcupacionSalida').sinMarco).toBe(true)
    expect(tipoTarjeta('Salida').sinMarco).toBeFalsy()
  })
})

describe('plantasClaseBPrimero', () => {
  it('pone delante la planta con clase B', () => {
    expect(plantasClaseBPrimero([asiento(1, 1, 'A'), asiento(2, 2, 'B')])).toEqual([2, 1])
    expect(plantasClaseBPrimero([asiento(1, 1, 'B'), asiento(2, 2, 'A')])).toEqual([1, 2])
    expect(plantasClaseBPrimero([asiento(1, 1, 'A')])).toEqual([1])
  })
})

describe('tarjetas del chat', () => {
  it('ocupación por vendedor: cifras, una fila por vendedor y el total', () => {
    const cuenta = (total: number, extra: Record<string, number> = {}) => ({ reasignados: 0, anulados: 0, vouchers: 0, cortesias: 0, intermedias: 0, claseA: total, claseB: 0, total, ...extra })
    const datos = {
      titulo: 'Guatemala → Flores',
      salida: '2026-10-29T14:00:00-06:00',
      bus: '101',
      empresa: 'FDN',
      asientos: 40,
      ocupados: 3,
      conIntermedias: 1,
      libres: 37,
      porCanal: { estacion: 2, agencia: 1, web: 0 },
      columnas: { reasignados: 'Reasignados', anulados: 'Anulados', vouchers: 'Vouchers', cortesias: 'Cortesías', intermedias: 'Intermedias', claseA: 'Clase A', claseB: 'Clase B', total: 'Total' },
      filas: [
        { usuario: 'Ana', cuenta: cuenta(2, { cortesias: 1 }) },
        { usuario: 'Luis', cuenta: cuenta(1, { intermedias: 1 }) },
      ],
      totales: cuenta(3, { cortesias: 1, intermedias: 1 }),
    }
    const w = mount(TarjetaOcupacionSalida, { props: { datos }, global })
    expect(w.text()).toContain('bus 101 · FDN')
    expect(w.findAll('.cifra').map((c) => c.text())).toEqual(['3Ocupados', '37Libres', '1Intermedios', '2Estación', '1Agencia', '0Web'])
    const filas = w.findAll('.fila').map((f) => f.findAll('.celda').map((c) => c.text()))
    expect(filas).toHaveLength(4)
    expect(filas[1]).toEqual(['Ana', '0', '0', '0', '1', '0', '2', '0', '2'])
    expect(filas[3]).toEqual(['Total', '0', '0', '0', '1', '1', '3', '0', '3'])
    expect(w.find('[title="Cortesías"]').text()).toBe('Cort.')
  })

  it('ocupación por vendedor: sin ventas no hay tabla', () => {
    const datos = { titulo: 'A → B', salida: null, bus: null, empresa: null, asientos: 10, ocupados: 0, conIntermedias: 0, libres: 10, porCanal: {}, columnas: { total: 'Total' }, filas: [], totales: { total: 0 } }
    const w = mount(TarjetaOcupacionSalida, { props: { datos }, global })
    expect(w.text()).toContain('No se vendió ningún boleto')
    expect(w.find('.tabla').exists()).toBe(false)
  })

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
