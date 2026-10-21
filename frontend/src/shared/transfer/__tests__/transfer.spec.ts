import { describe, it, expect } from 'vitest'
import { dividirOpciones, type TransferOption } from '@/shared/transfer/transfer'

const opciones: TransferOption[] = [
  { label: 'Ver boletos', value: '/api/permisos/1' },
  { label: 'Crear salida', value: '/api/permisos/2' },
  { label: 'Anular boleto', value: '/api/permisos/3' },
]

describe('dividirOpciones', () => {
  it('separa lo asignado (en orden de selección) de lo disponible', () => {
    const { disponibles, asignadas } = dividirOpciones(opciones, [
      '/api/permisos/3',
      '/api/permisos/1',
    ])

    expect(asignadas.map((o) => o.label)).toEqual(['Anular boleto', 'Ver boletos'])
    expect(disponibles.map((o) => o.label)).toEqual(['Crear salida'])
  })

  it('ignora valores de la selección que no están entre las opciones', () => {
    const { asignadas } = dividirOpciones(opciones, ['/api/permisos/9'])

    expect(asignadas).toEqual([])
  })

  it('filtra ambos conjuntos sin distinguir mayúsculas', () => {
    const { disponibles, asignadas } = dividirOpciones(opciones, ['/api/permisos/1'], ' BOLETO ')

    expect(asignadas.map((o) => o.label)).toEqual(['Ver boletos'])
    expect(disponibles.map((o) => o.label)).toEqual(['Anular boleto'])
  })

  it('devuelve todo como disponible cuando no hay selección', () => {
    const { disponibles, asignadas } = dividirOpciones(opciones, [])

    expect(disponibles).toHaveLength(3)
    expect(asignadas).toEqual([])
  })
})
