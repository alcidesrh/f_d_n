import { describe, expect, it } from 'vitest'
import type { ElementoCroquis } from '@/core/croquis/types'
import {
  aCroquis,
  agregarFila,
  agregarPlanta,
  aplicarPlantilla,
  colocar,
  compactarNumeracion,
  crearEstado,
  elementoEn,
  eliminarFila,
  FILAS_BUS_NUEVO,
  insertarFila,
  mover,
  numerarPorPosicion,
  plantasEditor,
  quitar,
  quitarFila,
  quitarPlanta,
  rellenar,
  vaciar,
  type EstadoEditor,
} from '../editor'

const asiento = (
  numero: number,
  fila: number,
  columna: number,
  extra: Partial<ElementoCroquis> = {},
): ElementoCroquis =>
  ({
    tipo: 'asiento',
    id: null,
    numero,
    clase: 'A',
    planta: 1,
    fila,
    columna,
    ...extra,
  }) as ElementoCroquis

const pos = (fila: number, columna: number, planta = 1) => ({ planta, fila, columna })
const numeros = (estado: EstadoEditor) =>
  aCroquis(estado).flatMap((e) => (e.tipo === 'asiento' ? [e.numero] : []))

describe('crearEstado', () => {
  it('un bus nuevo arranca con una planta de filas por defecto y el marco de 5 columnas', () => {
    const estado = crearEstado()
    expect(plantasEditor(estado)).toEqual([1])
    expect(estado.filas[1]).toBe(FILAS_BUS_NUEVO)
    expect(estado.columnas).toBe(5)
  })

  it('toma filas por planta y columnas de los elementos', () => {
    const estado = crearEstado([asiento(1, 3, 1), asiento(2, 7, 6, { planta: 2 })])
    expect(estado.filas).toEqual({ 1: 3, 2: 7 })
    expect(estado.columnas).toBe(6)
  })
})

describe('colocar / mover / quitar', () => {
  it('un asiento nuevo toma el menor número libre', () => {
    let estado = crearEstado([asiento(1, 1, 1), asiento(3, 1, 2)])
    estado = colocar(estado, { tipo: 'asiento', clase: 'B' }, pos(1, 4)).estado
    expect(elementoEn(estado, pos(1, 4))).toMatchObject({ numero: 2, clase: 'B', id: null })
  })

  it('no coloca sobre una celda ocupada ni fuera de la rejilla', () => {
    const estado = crearEstado([asiento(1, 1, 1)])
    expect(colocar(estado, { tipo: 'puerta' }, pos(1, 1)).estado).toBe(estado)
    expect(colocar(estado, { tipo: 'puerta' }, pos(9, 1)).estado).toBe(estado)
    expect(colocar(estado, { tipo: 'puerta' }, pos(1, 1, 2)).estado).toBe(estado)
  })

  it('el chofer es uno solo: tomar otro de la pila mueve el existente', () => {
    let estado = crearEstado()
    estado = colocar(estado, { tipo: 'chofer' }, pos(1, 1)).estado
    estado = colocar(estado, { tipo: 'chofer' }, pos(1, 2)).estado
    const choferes = estado.elementos.filter((e) => e.tipo === 'chofer')
    expect(choferes).toHaveLength(1)
    expect(choferes[0]).toMatchObject({ fila: 1, columna: 2 })
  })

  it('mover a una celda ocupada intercambia los elementos', () => {
    const estado = crearEstado([asiento(1, 1, 1), asiento(2, 1, 2)])
    const [a] = estado.elementos
    const movido = mover(estado, a!.uid, pos(1, 2))
    expect(elementoEn(movido, pos(1, 2))).toMatchObject({ numero: 1 })
    expect(elementoEn(movido, pos(1, 1))).toMatchObject({ numero: 2 })
  })

  it('no quita asientos con boletos vendidos', () => {
    const estado = crearEstado([asiento(1, 1, 1, { id: 9, conBoletos: true }), asiento(2, 1, 2)])
    const [vendido, libre] = estado.elementos
    expect(quitar(estado, vendido!.uid)).toBe(estado)
    expect(quitar(estado, libre!.uid).elementos).toHaveLength(1)
    expect(vaciar(estado).elementos.map((e) => e.uid)).toEqual([vendido!.uid])
  })
})

describe('numeración', () => {
  const desordenado = () =>
    crearEstado([
      asiento(9, 2, 1),
      asiento(4, 1, 2),
      asiento(7, 1, 1),
      asiento(1, 1, 1, { planta: 2 }),
    ])

  it('compactar conserva el orden relativo de los números', () => {
    expect(numeros(compactarNumeracion(desordenado()))).toEqual([3, 2, 4, 1])
  })

  it('por posición numera en orden de lectura (planta, fila, columna)', () => {
    const estado = numerarPorPosicion(desordenado())
    expect(elementoEn(estado, pos(1, 1))).toMatchObject({ numero: 1 })
    expect(elementoEn(estado, pos(1, 2))).toMatchObject({ numero: 2 })
    expect(elementoEn(estado, pos(2, 1))).toMatchObject({ numero: 3 })
    expect(elementoEn(estado, pos(1, 1, 2))).toMatchObject({ numero: 4 })
  })
})

describe('rejilla', () => {
  it('agrega y quita filas (solo la última si está vacía)', () => {
    let estado = crearEstado([asiento(1, 2, 1)])
    estado = agregarFila(estado, 1)
    expect(estado.filas[1]).toBe(3)
    estado = quitarFila(estado, 1)
    expect(estado.filas[1]).toBe(2)
    expect(quitarFila(estado, 1)).toBe(estado)
  })

  it('insertar y eliminar filas desplaza las siguientes', () => {
    let estado = crearEstado([asiento(1, 1, 1), asiento(2, 2, 1)])
    estado = insertarFila(estado, 1, 2)
    expect(elementoEn(estado, pos(3, 1))).toMatchObject({ numero: 2 })
    expect(eliminarFila(estado, 1, 1)).toBe(estado)
    estado = eliminarFila(estado, 1, 2)
    expect(elementoEn(estado, pos(2, 1))).toMatchObject({ numero: 2 })
    expect(estado.filas[1]).toBe(2)
  })

  it('segunda planta: se agrega con el alto de la baja y solo se quita vacía', () => {
    let estado = crearEstado([asiento(1, 4, 1)])
    estado = agregarPlanta(estado)
    expect(estado.filas).toEqual({ 1: 4, 2: 4 })
    expect(agregarPlanta(estado)).toBe(estado)
    const conAsiento = colocar(estado, { tipo: 'asiento', clase: 'A' }, pos(1, 1, 2)).estado
    expect(quitarPlanta(conAsiento, 2)).toBe(conAsiento)
    expect(plantasEditor(quitarPlanta(estado, 2))).toEqual([1])
    expect(quitarPlanta(estado, 1)).toBe(estado)
  })

  it('rellenar pone asientos 2 + 2 salvando el pasillo y las filas con señales', () => {
    let estado = crearEstado()
    estado = { ...estado, filas: { 1: 3 } }
    estado = colocar(estado, { tipo: 'chofer' }, pos(1, 1)).estado
    estado = rellenar(estado, 1, 'A')
    const asientos = aCroquis(estado).filter((e) => e.tipo === 'asiento')
    expect(asientos).toHaveLength(8)
    expect(asientos.some((a) => a.columna === 3)).toBe(false)
    expect(asientos.some((a) => a.fila === 1)).toBe(false)
    expect(numeros(estado)).toEqual([1, 2, 3, 4, 5, 6, 7, 8])
  })
})

describe('aplicarPlantilla', () => {
  it('conserva los ids de los asientos por número y de las señales por tipo', () => {
    const estado = crearEstado([
      asiento(1, 1, 1, { id: 11, conBoletos: true }),
      asiento(2, 1, 2, { id: 12 }),
      { tipo: 'puerta', id: 5, planta: 1, fila: 1, columna: 5 },
    ])
    const plantilla: ElementoCroquis[] = [
      { tipo: 'puerta', id: null, planta: 1, fila: 1, columna: 1 },
      asiento(1, 2, 1),
      asiento(3, 2, 2),
    ]
    const { estado: nuevo, conBoletosPerdidos } = aplicarPlantilla(estado, plantilla)
    expect(conBoletosPerdidos).toEqual([])
    expect(elementoEn(nuevo, pos(2, 1))).toMatchObject({ numero: 1, id: 11, conBoletos: true })
    expect(elementoEn(nuevo, pos(2, 2))).toMatchObject({ numero: 3, id: null })
    expect(elementoEn(nuevo, pos(1, 1))).toMatchObject({ tipo: 'puerta', id: 5 })
  })

  it('avisa de los asientos con boletos que la plantilla no tiene', () => {
    const estado = crearEstado([asiento(40, 1, 1, { id: 1, conBoletos: true })])
    expect(aplicarPlantilla(estado, [asiento(1, 1, 1)]).conBoletosPerdidos).toEqual([40])
  })
})
