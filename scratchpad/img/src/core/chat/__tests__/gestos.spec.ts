import { describe, expect, it } from 'vitest'
import { MAX_DESPLAZAMIENTO, UMBRAL_RESPUESTA, desplazamientoRespuesta, ejeDe, respondeAl, vuelveAlSoltar } from '../gestos'

describe('ejeDe', () => {
  it('no decide mientras el dedo apenas se movió', () => {
    expect(ejeDe(4, 5)).toBeNull()
  })

  it('horizontal o vertical según lo que domina', () => {
    expect(ejeDe(20, 6)).toBe('x')
    expect(ejeDe(-20, 6)).toBe('x')
    expect(ejeDe(6, 20)).toBe('y')
  })
})

describe('deslizar para responder', () => {
  it('solo hacia la derecha', () => {
    expect(desplazamientoRespuesta(-30)).toBe(0)
  })

  it('sigue al dedo hasta el umbral y luego con resistencia, sin pasar del máximo', () => {
    expect(desplazamientoRespuesta(40)).toBe(40)
    expect(desplazamientoRespuesta(UMBRAL_RESPUESTA + 20)).toBeLessThan(UMBRAL_RESPUESTA + 20)
    expect(desplazamientoRespuesta(1000)).toBe(MAX_DESPLAZAMIENTO)
  })

  it('responde al llegar al umbral', () => {
    expect(respondeAl(UMBRAL_RESPUESTA - 1)).toBe(false)
    expect(respondeAl(UMBRAL_RESPUESTA)).toBe(true)
  })
})

describe('volver desde el borde', () => {
  it('pasando un tercio del ancho', () => {
    expect(vuelveAlSoltar(150, 400, 1000)).toBe(true)
    expect(vuelveAlSoltar(100, 400, 1000)).toBe(false)
  })

  it('con un golpe rápido aunque sea corto', () => {
    expect(vuelveAlSoltar(60, 400, 80)).toBe(true)
    expect(vuelveAlSoltar(30, 400, 20)).toBe(false)
  })

  it('nunca hacia la izquierda', () => {
    expect(vuelveAlSoltar(-200, 400, 50)).toBe(false)
  })
})
