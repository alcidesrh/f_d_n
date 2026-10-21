// @vitest-environment node
// Puro: sin DOM.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { crearAgenda } from '@/core/metadata/agenda'

describe('crearAgenda', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('junta los avisos seguidos en una sola tarea, tras el silencio', () => {
    const tarea = vi.fn<() => void>()
    const agenda = crearAgenda(tarea, { espera: 1000, maximo: 5000 })
    agenda.programar()
    vi.advanceTimersByTime(400)
    agenda.programar()
    vi.advanceTimersByTime(900)
    expect(tarea).not.toHaveBeenCalled()
    vi.advanceTimersByTime(100)
    expect(tarea).toHaveBeenCalledOnce()
  })

  it('una ráfaga larga corre como mucho al máximo desde el primer aviso', () => {
    const tarea = vi.fn<() => void>()
    const agenda = crearAgenda(tarea, { espera: 1000, maximo: 3000 })
    for (let i = 0; i < 10; i++) {
      agenda.programar()
      vi.advanceTimersByTime(500)
    }
    // A los 3 s del primero ya corrió, aunque los avisos no pararon.
    expect(tarea).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(1000)
    expect(tarea).toHaveBeenCalledTimes(2)
  })

  it('cancelar descarta lo programado', () => {
    const tarea = vi.fn<() => void>()
    const agenda = crearAgenda(tarea, { espera: 1000, maximo: 5000 })
    agenda.programar()
    agenda.cancelar()
    vi.advanceTimersByTime(10_000)
    expect(tarea).not.toHaveBeenCalled()
  })
})
