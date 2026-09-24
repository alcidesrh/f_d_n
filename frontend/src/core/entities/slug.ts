/**
 * Conversión entre el nombre de entidad (PascalCase, ej: `BoletoAsiento`) y el
 * slug kebab-case usado en URLs (ej: `boleto-asiento`).
 */

/** `BoletoAsiento` → `boleto-asiento`. Concatenaciones de siglas tipo `APIClient`
 *  producen `api-client`; los nombres ya en minúsculas pasan sin cambios. */
export function entitySlug(name: string): string {
  return name.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase();
}

/** `boleto-asiento` → `BoletoAsiento`. Acepta separadores `-`, `_`, espacio y
 *  nombres ya en PascalCase (passthrough idempotente). */
export function entityNameFromSlug(slug: string): string {
  return slug
    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
    .replace(/[-_ ]+/g, " ")
    .trim()
    .split(" ")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join("");
}