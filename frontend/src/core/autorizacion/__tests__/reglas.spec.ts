import { describe, expect, it } from 'vitest'
import { HttpError } from '@/core/http'
import { autorizableBoletos, bloqueoAutorizable, cubreBoletos, pendientesDe, queSePide, vigencia } from '../reglas'

const autorizable = { capacidad: 'boleto.anular', operacion: 'anular boletos', dispensa: 'hora', dispensaNombre: 'Fuera de hora', sujeto: 'boletos', ids: [3], cantidad: 1, pendiente: null }

describe('autorizaciones', () => {
  it('reconoce un bloqueo que admite autorización y nada más', () => {
    const e = new HttpError(409, { error: 'La salida ya empezó', codigo: 'salida_iniciada', autorizable }, 'La salida ya empezó')
    expect(bloqueoAutorizable(e)).toEqual({ autorizable, mensaje: 'La salida ya empezó' })
    expect(bloqueoAutorizable(new HttpError(403, { error: 'No', codigo: 'permiso' }, 'No'))).toBeNull()
    expect(bloqueoAutorizable(new HttpError(409, { autorizable: { ...autorizable, ids: [] } }, 'x'))).toBeNull()
    expect(bloqueoAutorizable(new Error('red'))).toBeNull()
  })

  it('arma lo que se pide sobre boletos sin haber intentado la operación', () => {
    const a = autorizableBoletos('boleto.reasignar', 'permiso', [5, 3, 5])
    expect(a).toMatchObject({ capacidad: 'boleto.reasignar', operacion: 'reasignar boletos', dispensa: 'permiso', sujeto: 'boletos', ids: [3, 5], cantidad: 2 })
  })

  it('dice qué se pide', () => {
    expect(queSePide(autorizableBoletos('boleto.anular', 'hora', [1, 2]))).toBe('Anular 2 boletos')
    expect(queSePide(autorizableBoletos('boleto.reasignar', 'hora', [1]))).toBe('Reasignar 1 boleto')
    expect(queSePide({ capacidad: 'venta.cortesia', operacion: 'emitir cortesías', sujeto: 'salida', ids: [1230], cantidad: 1 })).toBe('Emitir 1 cortesía en la salida 1230')
  })

  it('la campana cuenta lo que espera al usuario', () => {
    expect(pendientesDe(null)).toBe(0)
    expect(pendientesDe({ porResolver: 2, aprobadas: 1, pendientes: 4, aprueba: true })).toBe(3)
  })

  it('la vigencia solo de las abiertas', () => {
    const ahora = new Date('2026-10-22T08:00:00-06:00')
    expect(vigencia({ estado: 'pendiente', venceEn: '2026-10-22T11:00:00-06:00' }, ahora)).toBe('vence en 3 h')
    expect(vigencia({ estado: 'aprobada', venceEn: '2026-10-22T08:25:00-06:00' }, ahora)).toBe('vence en 25 min')
    expect(vigencia({ estado: 'aprobada', venceEn: '2026-10-22T07:00:00-06:00' }, ahora)).toBe('venció')
    expect(vigencia({ estado: 'usada', venceEn: '2026-10-22T11:00:00-06:00' }, ahora)).toBeNull()
  })

  it('un pase aprobado cubre los boletos que pidió', () => {
    const pase = { estado: 'aprobada' as const, capacidad: 'boleto.anular', sujeto: 'boletos' as const, ids: [3, 4] }
    expect(cubreBoletos(pase, 'boleto.anular', [3])).toBe(true)
    expect(cubreBoletos(pase, 'boleto.anular', [3, 9])).toBe(false)
    expect(cubreBoletos(pase, 'boleto.reasignar', [3])).toBe(false)
    expect(cubreBoletos({ ...pase, estado: 'usada' }, 'boleto.anular', [3])).toBe(false)
  })
})
