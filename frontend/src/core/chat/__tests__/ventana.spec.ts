import { describe, expect, it } from 'vitest'
import { BARRA, MARGEN, MINIMA, cajaDe, encajar, flotanteInicial, redimensionar, type EstadoVentana } from '../ventana'

const escritorio = { w: 1440, h: 900 }
const movil = { w: 390, h: 800 }
const estado = (extra: Partial<EstadoVentana> = {}): EstadoVentana => ({ modo: 'esquina', minimizada: false, maximizada: false, flotante: null, ...extra })

describe('cajaDe', () => {
  it('esquina: abajo a la derecha; minimizada asoma solo la barra', () => {
    const abierta = cajaDe(estado(), escritorio)
    expect(abierta.x + abierta.w).toBe(escritorio.w - MARGEN)
    expect(abierta.y + abierta.h).toBe(escritorio.h - MARGEN)
    const min = cajaDe(estado({ minimizada: true }), escritorio)
    expect(min).toMatchObject({ x: abierta.x, w: abierta.w, h: BARRA, y: escritorio.h - BARRA })
  })

  it('maximizada ocupa la pantalla; minimizar gana a maximizar', () => {
    expect(cajaDe(estado({ maximizada: true }), escritorio)).toEqual({ x: 0, y: 0, ...escritorio })
    expect(cajaDe(estado({ maximizada: true, minimizada: true }), escritorio).h).toBe(BARRA)
  })

  it('flotante: donde la dejó; minimizada se encoge en el mismo lugar', () => {
    const flotante = { x: 300, y: 200, w: 500, h: 600 }
    expect(cajaDe(estado({ modo: 'flotante', flotante }), escritorio)).toEqual(flotante)
    const min = cajaDe(estado({ modo: 'flotante', flotante, minimizada: true }), escritorio)
    expect(min).toMatchObject({ x: 300, y: 200, h: BARRA })
    expect(min.w).toBeLessThan(flotante.w)
  })

  it('flotante que quedó fuera (pantalla más chica) vuelve a entrar', () => {
    const c = cajaDe(estado({ modo: 'flotante', flotante: { x: 1300, y: 850, w: 500, h: 600 } }), { w: 1024, h: 700 })
    expect(c.x + c.w).toBeLessThanOrEqual(1024)
    expect(c.y + c.h).toBeLessThanOrEqual(700)
  })

  it('en el móvil, abierta ocupa la pantalla en cualquier modo', () => {
    expect(cajaDe(estado({ modo: 'flotante' }), movil)).toEqual({ x: 0, y: 0, ...movil })
    expect(cajaDe(estado(), movil)).toEqual({ x: 0, y: 0, ...movil })
    expect(cajaDe(estado({ minimizada: true }), movil).h).toBe(BARRA)
  })
})

describe('encajar y flotanteInicial', () => {
  it('respeta el tamaño mínimo y la pantalla', () => {
    expect(encajar({ x: -50, y: -10, w: 100, h: 100 }, escritorio)).toEqual({ x: 0, y: 0, w: MINIMA.w, h: MINIMA.h })
    expect(encajar({ x: 0, y: 0, w: 5000, h: 5000 }, escritorio)).toEqual({ x: 0, y: 0, ...escritorio })
  })

  it('la inicial cabe en la pantalla', () => {
    const c = flotanteInicial(escritorio)
    expect(c.x).toBeGreaterThanOrEqual(0)
    expect(c.x + c.w).toBeLessThanOrEqual(escritorio.w)
  })
})

describe('redimensionar', () => {
  const inicio = { x: 400, y: 200, w: 500, h: 500 }

  it('el borde opuesto queda quieto', () => {
    expect(redimensionar(inicio, 'se', 50, 30, escritorio)).toEqual({ x: 400, y: 200, w: 550, h: 530 })
    expect(redimensionar(inicio, 'nw', -40, -20, escritorio)).toEqual({ x: 360, y: 180, w: 540, h: 520 })
  })

  it('no baja del mínimo ni sale de la pantalla', () => {
    const chica = redimensionar(inicio, 'w', 400, 0, escritorio)
    expect(chica.w).toBe(MINIMA.w)
    expect(chica.x + chica.w).toBe(inicio.x + inicio.w)
    expect(redimensionar(inicio, 'e', 5000, 0, escritorio).w).toBe(escritorio.w - inicio.x)
    expect(redimensionar(inicio, 'n', 0, -5000, escritorio).y).toBe(0)
  })
})
