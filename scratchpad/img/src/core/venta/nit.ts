/**
 * NIT guatemalteco (espejo de `App\Venta\Comprador::nitValido`): dígitos +
 * dígito verificador (0–9 o K) por módulo 11. `CF` = consumidor final.
 */
export function normalizarNit(nit: string | null | undefined): string {
  const n = (nit ?? '').replace(/[\s-]+/g, '').toUpperCase()
  return n === '' || n === 'C/F' ? 'CF' : n
}

export function nitValido(nit: string): boolean {
  const n = normalizarNit(nit)
  if (n === 'CF') return true
  const m = /^(\d+)([\dK])$/.exec(n)
  if (!m) return false
  const cuerpo = [...m[1]!].reverse()
  const suma = cuerpo.reduce((acc, d, i) => acc + Number(d) * (i + 2), 0)
  const v = (11 - (suma % 11)) % 11
  return m[2] === (v === 10 ? 'K' : String(v))
}
