import { describe, expect, it } from 'vitest'
import { estadoItem, etiquetaDia, mensajeResultado, porDia, resumenPrevia } from '../plan'
import type { ItemPlan, ResultadoProgramacion } from '../types'

const item = (fecha: string, estado: ItemPlan['estado'], id: number | null = null): ItemPlan => ({ fecha, busId: 1, bus: '0001', estado, choque: null, id })

const resultado = (items: ItemPlan[]): ResultadoProgramacion => ({
  creadas: items.filter((i) => i.estado === 'nueva').length,
  items,
  resumen: { total: items.length, nueva: 0, existe: 0, conflicto: 0, pasada: 0 },
  omitidas: items.filter((i) => i.estado !== 'nueva'),
  esquema: null,
})

describe('plan del programador', () => {
  it('en el resultado, lo que esta operación creó es «creada», no «ya existe»', () => {
    expect(estadoItem(item('2027-03-20T06:10:00-06:00', 'nueva', 1362))).toBe('creada')
    expect(estadoItem(item('2027-03-20T06:10:00-06:00', 'nueva'))).toBe('nueva')
    expect(estadoItem(item('2027-03-20T06:10:00-06:00', 'existe'))).toBe('existe')
  })

  it('agrupa por día y cuenta omitidas y problemas', () => {
    const dias = porDia([
      item('2027-03-20T06:10:00-06:00', 'nueva', 1),
      item('2027-03-20T08:10:00-06:00', 'existe'),
      item('2027-03-21T06:10:00-06:00', 'conflicto'),
    ])
    expect(dias.map((d) => [d.dia, d.items.length, d.omitidas, d.problemas])).toEqual([
      ['2027-03-20', 2, 1, 0],
      ['2027-03-21', 1, 1, 1],
    ])
    // «Ya existe» no es un problema: la etiqueta del día no es una advertencia.
    expect(etiquetaDia(dias[0]!)).toEqual({ texto: '1 se omite', severidad: 'secondary' })
    expect(etiquetaDia(dias[1]!)).toEqual({ texto: '1 se omite', severidad: 'warn' })
    expect(etiquetaDia(porDia([item('2027-03-20T06:10:00-06:00', 'nueva', 1)])[0]!)).toBeNull()
  })

  it('resume la vista previa solo con lo que hay', () => {
    expect(resumenPrevia({ total: 4, nueva: 3, existe: 1, conflicto: 0, pasada: 0 }).map((t) => t.texto)).toEqual(['3 nuevas', '1 ya existe'])
    expect(resumenPrevia({ total: 1, nueva: 1, existe: 0, conflicto: 0, pasada: 0 }).map((t) => t.texto)).toEqual(['1 nueva'])
  })

  it('el aviso tras crear dice lo creado y lo omitido por motivo', () => {
    const creadas = [item('2027-03-20T06:10:00-06:00', 'nueva', 1), item('2027-03-21T06:10:00-06:00', 'nueva', 2), item('2027-03-22T06:10:00-06:00', 'nueva', 3)]
    expect(mensajeResultado(resultado(creadas))).toEqual({ severidad: 'success', texto: 'Se crearon 3 salidas.' })
    expect(mensajeResultado(resultado([creadas[0]!]))).toEqual({ severidad: 'success', texto: 'Se creó 1 salida.' })
    expect(mensajeResultado(resultado([...creadas, item('2027-03-23T06:10:00-06:00', 'existe'), item('2027-03-24T06:10:00-06:00', 'conflicto')]))).toEqual({
      severidad: 'success',
      texto: 'Se crearon 3 salidas. No se crearon 2: 1 ya existía y 1 tenía el bus ocupado.',
    })
  })

  it('sin creadas: informativo si todas ya existían, advertencia si hubo problemas', () => {
    expect(mensajeResultado(resultado([item('2027-03-20T06:10:00-06:00', 'existe'), item('2027-03-21T06:10:00-06:00', 'existe')]))).toEqual({
      severidad: 'info',
      texto: 'No se creó ninguna salida. No se crearon 2: 2 ya existían.',
    })
    expect(mensajeResultado(resultado([item('2027-03-20T06:10:00-06:00', 'pasada')])).severidad).toBe('warn')
  })
})
