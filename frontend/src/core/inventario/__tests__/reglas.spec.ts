import { describe, expect, it } from 'vitest'
import { borradorVacio, cantidad, cantidadValida, conSigno, faltaEnNiveles, faltaParaRegistrar, nivelesDe, nombreAlmacen, quetzales, registroDe, tiposPermitidos } from '../reglas'

describe('tiposPermitidos', () => {
  it('mover da entradas, salidas y envíos; ajustar, el conteo', () => {
    expect(tiposPermitidos({ mover: true, ajustar: false, niveles: false })).toEqual(['entrada', 'salida', 'envio'])
    expect(tiposPermitidos({ mover: true, ajustar: true, niveles: false })).toEqual(['entrada', 'salida', 'envio', 'ajuste'])
    expect(tiposPermitidos({ mover: false, ajustar: true, niveles: true })).toEqual(['ajuste'])
    expect(tiposPermitidos({ mover: false, ajustar: false, niveles: true })).toEqual([])
  })

  it('un almacén inactivo solo se cuenta', () => {
    expect(tiposPermitidos({ mover: true, ajustar: true, niveles: true }, false)).toEqual(['ajuste'])
  })
})

describe('cantidades y dinero', () => {
  it('acepta hasta los decimales que guarda el backend', () => {
    expect(cantidadValida('12.5')).toBe(true)
    expect(cantidadValida('1,200')).toBe(true)
    expect(cantidadValida('12.505')).toBe(false)
    expect(cantidadValida('-1')).toBe(false)
    expect(cantidadValida('')).toBe(false)
    expect(cantidadValida('3.1234', 4)).toBe(true)
  })

  it('escribe sin ceros de más y con signo en el libro', () => {
    expect(cantidad('12.50')).toBe('12.5')
    expect(cantidad('3.00')).toBe('3')
    expect(cantidad(null)).toBe('—')
    expect(conSigno('-5.00')).toBe('−5')
    expect(conSigno('2.50')).toBe('+2.5')
    expect(conSigno('0.00')).toBe('0')
  })

  it('el dinero en quetzales; el costo unitario con hasta 4 decimales', () => {
    expect(quetzales('1234.5')).toBe('Q1,234.50')
    expect(quetzales('3.3333', true)).toBe('Q3.3333')
    expect(quetzales('3.5000', true)).toBe('Q3.50')
  })

  it('el almacén con su empresa cuando hace falta distinguir', () => {
    const a = { nombre: 'Central', empresa: { nombre: 'Del Norte' } }
    expect(nombreAlmacen(a)).toBe('Central')
    expect(nombreAlmacen(a, true)).toBe('Central · Del Norte')
  })
})

describe('registrar', () => {
  it('pide lo de cada tipo', () => {
    const b = borradorVacio('entrada')
    expect(faltaParaRegistrar(b)).toBe('Elija el artículo.')
    expect(faltaParaRegistrar({ ...b, articulo: 1, cantidad: '0' })).toBe('La cantidad debe ser mayor que cero.')
    expect(faltaParaRegistrar({ ...b, articulo: 1, cantidad: '10' })).toBe('Escriba el costo por unidad (hasta 4 decimales).')
    expect(faltaParaRegistrar({ ...b, articulo: 1, cantidad: '10', costo: '0' })).toBeNull()
    expect(faltaParaRegistrar({ ...borradorVacio('envio', 1), cantidad: '2' })).toBe('Elija a qué almacén se envía.')
    expect(faltaParaRegistrar({ ...borradorVacio('ajuste', 1), cantidad: '0' })).toBe('Diga por qué se ajusta (conteo, merma, rotura…).')
    expect(faltaParaRegistrar({ ...borradorVacio('ajuste', 1), cantidad: '0', motivo: 'Conteo de fin de mes' })).toBeNull()
  })

  it('manda solo lo que aplica al tipo', () => {
    const entrada = { ...borradorVacio('entrada', 7), cantidad: ' 1,200 ', costo: '3.5', destino: 4, documento: ' FAC-1 ' }
    expect(registroDe(3, entrada)).toEqual({ tipo: 'entrada', almacen: 3, articulo: 7, cantidad: '1200', costo: '3.5', documento: 'FAC-1' })
    const envio = { ...borradorVacio('envio', 7), cantidad: '2', costo: '9', destino: 4, motivo: ' Para la avería ' }
    expect(registroDe(3, envio)).toEqual({ tipo: 'envio', almacen: 3, articulo: 7, cantidad: '2', destino: 4, motivo: 'Para la avería' })
  })
})

describe('niveles', () => {
  it('van en orden, como los valida el backend', () => {
    expect(faltaEnNiveles({ minimo: '10', puntoReorden: '15', maximo: '50' })).toBeNull()
    expect(faltaEnNiveles({ minimo: '', puntoReorden: '', maximo: '' })).toBeNull()
    expect(faltaEnNiveles({ minimo: '10', puntoReorden: '5', maximo: '' })).toBe('El punto de reorden va en el mínimo o por encima.')
    expect(faltaEnNiveles({ minimo: '10', puntoReorden: '', maximo: '5' })).toBe('El máximo va por encima del mínimo y del punto de reorden.')
    expect(faltaEnNiveles({ minimo: '10', puntoReorden: '20', maximo: '15' })).toBe('El máximo va por encima del mínimo y del punto de reorden.')
    expect(faltaEnNiveles({ minimo: 'diez', puntoReorden: '', maximo: '' })).toBe('Cada nivel es un número con hasta 2 decimales.')
  })

  it('para editar, sin ceros de más y el mínimo cero vacío', () => {
    expect(nivelesDe({ minimo: '10.00', puntoReorden: '15.50', maximo: null })).toEqual({ minimo: '10', puntoReorden: '15.5', maximo: '' })
    expect(nivelesDe({ minimo: '0.00', puntoReorden: null, maximo: null })).toEqual({ minimo: '', puntoReorden: '', maximo: '' })
    expect(nivelesDe(null)).toEqual({ minimo: '', puntoReorden: '', maximo: '' })
  })
})
