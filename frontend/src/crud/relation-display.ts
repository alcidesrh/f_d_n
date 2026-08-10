import { shortId } from '@graphql-orm/core';

/** Nombre legible de una entidad relacionada: nombre ?? label ?? name ?? title ?? username ?? id. */
export function displayName(item: Record<string, unknown> | null | undefined): string {
  if (!item) return '—';
  for (const key of ['nombre', 'label', 'name', 'title', 'username', 'id']) {
    const v = item[key];
    if (v !== undefined && v !== null && String(v).trim() !== '') return String(v);
  }
  return '—';
}

export function relationId(item: Record<string, unknown> | null | undefined): string {
  return (item?.id as string) ?? '';
}

/** Id numérico corto (desde el IRI) para ordenar "id desc". NaN si no es numérico. */
export function numericSortId(item: Record<string, unknown> | null | undefined): number {
  const id = item?.id as string | undefined;
  if (!id) return NaN;
  const n = Number(shortId(id));
  return Number.isFinite(n) ? n : 0;
}
