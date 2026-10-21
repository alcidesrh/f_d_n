/**
 * Junta avisos seguidos en una sola tarea: corre cuando pasan `espera` ms sin
 * otro `programar()`, y como mucho `maximo` ms después del primero (una
 * ráfaga larga no la aplaza sin fin).
 */
export interface Agenda {
  programar(): void
  cancelar(): void
}

export function crearAgenda(tarea: () => void, { espera, maximo }: { espera: number; maximo: number }): Agenda {
  let timer: ReturnType<typeof setTimeout> | undefined
  let primero: number | null = null

  function cancelar() {
    clearTimeout(timer)
    timer = undefined
    primero = null
  }

  return {
    programar() {
      const ahora = Date.now()
      primero ??= ahora
      clearTimeout(timer)
      timer = setTimeout(
        () => {
          cancelar()
          tarea()
        },
        Math.max(0, Math.min(espera, primero + maximo - ahora)),
      )
    },
    cancelar,
  }
}
