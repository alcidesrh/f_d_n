/** Color estable por empresa para los marcadores del mapa. */
const PALETA = ['#115e59', '#1d4ed8', '#b91c1c', '#b45309', '#7e22ce', '#0e7490', '#be185d', '#15803d', '#c2410c', '#3730a3']

/** Colores fijos por nombre de empresa (orange-600 para Maya de Oro). */
const POR_NOMBRE: Record<string, string> = { 'maya de oro': '#ea580c' }

export const colorEmpresa = (empresaId: number, nombre?: string): string =>
  POR_NOMBRE[nombre?.trim().toLowerCase() ?? ''] ?? PALETA[Math.abs(empresaId) % PALETA.length]!
