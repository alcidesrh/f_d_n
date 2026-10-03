/** Color estable por empresa para los marcadores del mapa. */
const PALETA = ['#115e59', '#1d4ed8', '#b91c1c', '#b45309', '#7e22ce', '#0e7490', '#be185d', '#15803d', '#c2410c', '#3730a3']

export const colorEmpresa = (empresaId: number): string => PALETA[Math.abs(empresaId) % PALETA.length]!
