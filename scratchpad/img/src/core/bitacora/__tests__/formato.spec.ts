import { describe, expect, it } from 'vitest'
import { lineasDeDetalle, quetzales, quien } from '../formato'

describe('bitácora: detalle legible', () => {
  it('cambio de bus de una salida', () => {
    expect(lineasDeDetalle('salida', { operacion: 'cambio_bus', detalle: { de: '42', a: '111' } })).toEqual(['Bus 42 → Bus 111'])
    expect(lineasDeDetalle('salida', { operacion: 'cambio_bus', detalle: { a: '111' } })).toEqual(['Sin bus → Bus 111'])
    expect(lineasDeDetalle('salida', { operacion: 'cambio_bus', detalle: { de: '42', a: '43', asientosPorNumero: 36 } })).toEqual(['Bus 42 → Bus 43', '36 asiento(s) pasados al mismo número'])
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

  it('abordaje: chequeado y no presentado', () => {
    expect(lineasDeDetalle('boleto', { operacion: 'chequeado', detalle: { asiento: 3, salida: 9, venta: 7 } })).toEqual(['Asiento 3', 'Venta 7'])
    expect(lineasDeDetalle('boleto', { operacion: 'cancelado', detalle: { asiento: 3, motivo: 'No se presentó a abordar.', venta: 7 } })).toEqual(['Asiento 3', 'Motivo: No se presentó a abordar.', 'Venta 7'])
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

describe('bitácora de accesos (ADR-039)', () => {
  it('una asignación otorgada: rol, alcance, vigencia y motivo', () => {
    const lineas = lineasDeDetalle('usuario', {
      operacion: 'asignacion_otorgada',
      detalle: { rol: 'ROLE_VENDEDOR_BOLETOS', empresas: [1, 2], estaciones: 'todas', hasta: '2026-12-31', motivo: 'Suplencia' },
    })

    expect(lineas).toEqual(['ROLE_VENDEDOR_BOLETOS · empresas 1, 2 · todas las estaciones', 'Vigencia: … a 2026-12-31', 'Motivo: Suplencia'])
  })

  it('una modificación muestra antes y ahora', () => {
    const lineas = lineasDeDetalle('usuario', {
      operacion: 'asignacion_modificada',
      detalle: { antes: { rol: 'A', empresas: [1], estaciones: [3] }, ahora: { rol: 'A', empresas: [1], estaciones: [3, 4] } },
    })

    expect(lineas).toEqual(['Antes: A · empresas 1 · estaciones 3', 'Ahora: A · empresas 1 · estaciones 3, 4'])
  })

  it('un rol modificado: lo que agrega y quita', () => {
    expect(lineasDeDetalle('rol', { operacion: 'modificado', detalle: { nombre: 'Vendedor', agregadas: ['boleto.ver'], quitadas: null } })).toEqual(['Vendedor', 'Agrega: boleto.ver'])
  })
})
