/** Color estable por empresa para los marcadores del mapa. */
const PALETA = ['#115e59', '#1d4ed8', '#b91c1c', '#b45309', '#7e22ce', '#0e7490', '#be185d', '#15803d', '#c2410c', '#3730a3']

/** Colores fijos por nombre de empresa (orange-600 para Maya de Oro). */
const POR_NOMBRE: Record<string, string> = { 'maya de oro': '#ea580c' }

export const colorEmpresa = (empresaId: number, nombre?: string): string =>
  POR_NOMBRE[nombre?.trim().toLowerCase() ?? ''] ?? PALETA[Math.abs(empresaId) % PALETA.length]!

/** `#rrggbb` mezclado con negro (`cuanto` de 0 a 1): la raya fina de una trayectoria resalta más sobre el mapa. */
export const oscurecer = (hex: string, cuanto: number): string =>
  '#' +
  [1, 3, 5]
    .map((i) =>
      Math.round(parseInt(hex.slice(i, i + 2), 16) * (1 - cuanto))
        .toString(16)
        .padStart(2, '0'),
    )
    .join('')

/** Los teléfonos de prueba (temporal, ADR-050): fuera de la paleta de las empresas. */
export const COLOR_TELEFONO = '#1e293b'
