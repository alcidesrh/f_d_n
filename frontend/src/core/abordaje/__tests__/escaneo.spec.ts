import { describe, expect, it } from 'vitest'
import { debeEnviar, limpiar, PAUSA_REPETIDO_MS, resumen } from '../escaneo'
import type { BoletoAbordaje } from '../types'

describe('escaneo de boletos', () => {
  it('limpia espacios y el Enter del lector', () => {
    expect(limpiar(' FDN1.42.abcdefABCDEF\n')).toBe('FDN1.42.abcdefABCDEF')
  })

  it('no reenvía la misma lectura hasta que pasa la pausa', () => {
    const ultima = { codigo: 'A', momento: 1000 }
    expect(debeEnviar('', null, 0)).toBe(false)
    expect(debeEnviar('A', null, 0)).toBe(true)
    expect(debeEnviar('A', ultima, 1500)).toBe(false)
    expect(debeEnviar('B', ultima, 1500)).toBe(true)
    expect(debeEnviar('A', ultima, 1000 + PAUSA_REPETIDO_MS)).toBe(true)
  })

  it('resume los boletos chequeados', () => {
    const b = (asiento: number, cliente: string | null) => ({ asiento, cliente }) as BoletoAbordaje
    expect(resumen([b(3, 'ANA'), b(4, null)])).toBe('Asiento 3 · ANA\nAsiento 4')
  })
})
