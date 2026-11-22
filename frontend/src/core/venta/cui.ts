/**
 * CUI del DPI (espejo de `App\Venta\IdentidadCliente::cuiValido`): 13 dígitos
 * (los separadores no cuentan); los 8 primeros, por su posición (2 a 9), dan
 * módulo 11 el noveno.
 */
export function cuiValido(texto: string): boolean {
  const cui = texto.replace(/[^0-9A-Za-z]/g, '')
  if (!/^\d{13}$/.test(cui)) return false
  let suma = 0
  for (let i = 0; i < 8; i++) suma += Number(cui[i]) * (i + 2)
  return suma % 11 === Number(cui[8])
}
