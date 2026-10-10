import { describe, expect, it } from 'vitest'
import { lineasDeDetalle, quetzales, quien } from '../formato'

describe('bitácora: detalle legible', () => {
  it('cambio de bus de una salida', () => {
    expect(lineasDeDetalle('salida', { operacion: 'cambio_bus', detalle: { de: '42', a: '111' } })).toEqual(['Bus 42 → Bus 111'])
    expect(lineasDeDetalle('salida', { operacion: 'cambio_bus', detalle: { a: '111' } })).toEqual(['Sin bus → Bus 111'])
  })

  it('cambio de estado de una salida', () => {
    expect(lineasDeDetalle('salida', { operacion: 'abordando', detalle: { de: 'programada', a: 'abordando' } })).toEqual(['Estado: programada → abordando'])
  })

  it('salida creada o eliminada: trayecto y bus', () => {
    expect(lineasDeDetalle('salida', { operacion: 'creada', detalle: { trayecto: 'A → B', bus: '42' } })).toEqual(['A → B', 'Bus 42'])
    expect(lineasDeDetalle('salida', { operacion: 'eliminada', detalle: null })).toEqual([])
  })

  it('boleto creado: asiento, trayecto, precio y canal', () => {
    expect(lineasDeDetalle('boleto', { operacion: 'creado', detalle: { asiento: 3, trayecto: 'A → B', precio: '27000', canal: 'web', venta: 7 } })).toEqual(['Asiento 3', 'A → B', 'Q 270.00', 'Vendido en la página web', 'Venta 7'])
  })

  it('boleto nacido de una reasignación', () => {
    expect(lineasDeDetalle('boleto', { operacion: 'creado', detalle: { asiento: 3, reasignadoDe: 81887 } })).toContain('Reemplaza al boleto 81887')
  })

  it('boleto reasignado: solo menciona la salida si cambió', () => {
    expect(lineasDeDetalle('boleto', { operacion: 'reasignado', detalle: { asiento: 1, haciaAsiento: 3, salida: 10, haciaSalida: 10 } })).toEqual(['Asiento 1 → asiento 3'])
    expect(lineasDeDetalle('boleto', { operacion: 'reasignado', detalle: { asiento: 1, haciaAsiento: 3, salida: 10, haciaSalida: 11 } })).toEqual(['Asiento 1 → asiento 3', 'A la salida 11'])
  })

  it('boleto anulado: motivo', () => {
    expect(lineasDeDetalle('boleto', { operacion: 'anulado', detalle: { asiento: 3, motivo: 'Pasajero desistió', venta: 7 } })).toEqual(['Asiento 3', 'Motivo: Pasajero desistió', 'Venta 7'])
  })

  it('importes y autor', () => {
    expect(quetzales('5050')).toBe('Q 50.50')
    expect(quetzales('x')).toBeNull()
    expect(quien({ usuario: { id: 1, username: 'ana', nombre: 'Ana Pérez' }, detalle: null })).toBe('Ana Pérez')
    expect(quien({ usuario: { id: null, username: null, nombre: 'Luis' }, detalle: null })).toBe('Luis')
    expect(quien({ usuario: null, detalle: { canal: 'web' } })).toBe('Página web')
    expect(quien({ usuario: null, detalle: null })).toBe('Sistema')
  })
})
