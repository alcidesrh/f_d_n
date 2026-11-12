import { describe, expect, it } from 'vitest'
import { HttpError } from '@/core/http'
import { bloqueoAutorizable, pendientesDe, queSePide, vigencia } from '../reglas'

const autorizable = { capacidad: 'boleto.anular', operacion: 'anular boletos', dispensa: 'hora', dispensaNombre: 'Fuera de hora', sujeto: 'boletos', ids: [3], cantidad: 1, pendiente: null }

describe('autorizaciones', () => {
  it('reconoce un bloqueo que admite autorización y nada más', () => {
    const e = new HttpError(409, { error: 'La salida ya empezó', codigo: 'salida_iniciada', autorizable }, 'La salida ya empezó')
    expect(bloqueoAutorizable(e)).toEqual({ autorizable, mensaje: 'La salida ya empezó' })
    expect(bloqueoAutorizable(new HttpError(403, { error: 'No', codigo: 'permiso' }, 'No'))).toBeNull()
    expect(bloqueoAutorizable(new HttpError(409, { autorizable: { ...autorizable, ids: [] } }, 'x'))).toBeNull()
    expect(bloqueoAutorizable(new Error('red'))).toBeNull()
    // Sobre encomiendas, fuera de estado (ADR-058).
    const encomienda = { ...autorizable, capacidad: 'encomienda.anular', operacion: 'anular encomiendas', dispensa: 'estado', dispensaNombre: 'Fuera de estado', sujeto: 'encomiendas', ids: [8] }
    expect(bloqueoAutorizable(new HttpError(409, { autorizable: encomienda }, 'Ya se entregó'))).toEqual({ autorizable: encomienda, mensaje: 'Ya se entregó' })
    expect(bloqueoAutorizable(new HttpError(409, { autorizable: { ...encomienda, sujeto: 'otra' } }, 'x'))).toBeNull()
  })

  it('dice qué se pide', () => {
    expect(queSePide({ capacidad: 'boleto.anular', operacion: 'anular boletos', sujeto: 'boletos', ids: [1, 2], cantidad: 2 })).toBe('Anular 2 boletos')
    expect(queSePide({ capacidad: 'boleto.reasignar', operacion: 'reasignar boletos', sujeto: 'boletos', ids: [1], cantidad: 1 })).toBe('Reasignar 1 boleto')
    expect(queSePide({ capacidad: 'venta.cortesia', operacion: 'emitir cortesías', sujeto: 'salida', ids: [1230], cantidad: 1 })).toBe('Emitir 1 cortesía en la salida 1230')
    expect(queSePide({ capacidad: 'encomienda.anular', operacion: 'anular encomiendas', sujeto: 'encomiendas', ids: [8], cantidad: 1 })).toBe('Anular 1 encomienda')
    expect(queSePide({ capacidad: 'encomienda.anular', operacion: 'anular encomiendas', sujeto: 'encomiendas', ids: [8, 9], cantidad: 2 })).toBe('Anular 2 encomiendas')
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
})
