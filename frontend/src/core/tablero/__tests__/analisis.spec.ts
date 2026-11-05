import { describe, expect, it } from 'vitest'
import { extremosDemanda, horasDemanda, resumenRutas } from '../analisis'
import type { CeldaDemanda, RutaTablero } from '../types'

const celda = (dia: number, hora: number, ocupacion: number | null, salidas = 3): CeldaDemanda => ({ dia, hora, salidas, ocupacion, boletos: 0 })
const ruta = (id: number, ingresos: number, senal: RutaTablero['senal'] = null): RutaTablero => ({
  id, nombre: `R${id}`, salidas: 5, boletos: 0, ingresos, ingresoPorSalida: 0, ocupacion: 0.5, ocupacionAnterior: null, senal,
})

describe('análisis del tablero', () => {
  it('extremos de demanda ignoran franjas con una sola salida', () => {
    const r = extremosDemanda([celda(1, 8, 0.9), celda(2, 3, 0.1), celda(5, 18, 1, 1), celda(3, 9, null)])
    expect(r.llena).toMatchObject({ dia: 1, hora: 8 })
    expect(r.vacia).toMatchObject({ dia: 2, hora: 3 })
    expect(extremosDemanda([])).toEqual({ llena: null, vacia: null })
  })

  it('horas contiguas del mapa', () => {
    expect(horasDemanda([celda(1, 5, 0.5), celda(2, 8, 0.5)])).toEqual([5, 6, 7, 8])
    expect(horasDemanda([])).toEqual([])
  })

  it('resumen de rutas: señales y concentración', () => {
    const r = resumenRutas([ruta(1, 700), ruta(2, 200, 'saturada'), ruta(3, 60, 'baja'), ruta(4, 40, 'baja')])
    expect(r).toEqual({ saturadas: 1, bajas: 2, ingresoBajas: 0.1, rutas80: 2 })
  })
})
