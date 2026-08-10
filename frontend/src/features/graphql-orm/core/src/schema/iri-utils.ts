// El "id" de API Platform GraphQL ES el IRI (sección 3.2) — no hay que decodificar base64.
export function shortId(iri: string): string {
  const segments = iri.split('/').filter(Boolean);
  return segments[segments.length - 1] ?? iri;
}

export function resourceTypeFromIri(iri: string): string | null {
  const segments = iri.split('/').filter(Boolean);
  return segments.length >= 2 ? (segments[segments.length - 2] ?? null) : null;
}
