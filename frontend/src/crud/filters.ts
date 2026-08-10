import type { CrudColumn } from './entity-meta';
import { displayName, relationId } from './relation-display';

export interface ColumnFilter {
  /** Búsqueda por texto (contiene, case-insensitive) para columnas string/number/enum. */
  text?: string;
  /** Multi-select: ids (relaciones), valores de enum o true/false. */
  values?: Array<string | number | boolean>;
  /** Combina los valores seleccionados de una misma columna. */
  valuesOperator?: 'OR' | 'AND';
  /** Rango de fecha (desde). */
  from?: string;
  /** Rango de fecha (hasta). */
  to?: string;
}

export type GlobalOperator = 'OR' | 'AND';

function toCompareValues(value: unknown): string[] {
  const arr = Array.isArray(value) ? value : [value];
  const out: string[] = [];
  for (const v of arr) {
    if (v && typeof v === 'object') {
      out.push(String(relationId(v as Record<string, unknown>) ?? ''));
      out.push(displayName(v as Record<string, unknown>));
    } else {
      out.push(String(v ?? ''));
    }
  }
  return out;
}

export function matchesValues(
  rowValue: unknown,
  selected: Array<string | number | boolean>,
  operator: 'OR' | 'AND',
): boolean {
  const compare = toCompareValues(rowValue).map((v) => v.toLowerCase());
  const wanted = selected.map((s) => String(s).toLowerCase()).filter(Boolean);
  if (wanted.length === 0) return true;
  const hit = (w: string) => compare.some((v) => v === w);
  return operator === 'AND' ? wanted.every(hit) : wanted.some(hit);
}

export function isColumnFilterActive(filter: ColumnFilter | undefined): boolean {
  if (!filter) return false;
  const { text, values, from, to } = filter;
  if (text !== undefined && text.trim() !== '') return true;
  if (values !== undefined && values.length > 0) return true;
  if (from !== undefined && from !== '') return true;
  if (to !== undefined && to !== '') return true;
  return false;
}

export function columnMatches(row: Record<string, unknown>, col: CrudColumn, filter: ColumnFilter): boolean {
  const raw = row[col.field];

  if (col.kind === 'date') {
    const value = String(raw ?? '');
    const day = value.slice(0, 10);
    if (filter.from && filter.from !== '' && day < filter.from) return false;
    if (filter.to && filter.to !== '' && day > filter.to) return false;
    return true;
  }

  if (col.kind === 'relation' || col.kind === 'enum') {
    if (filter.values && filter.values.length > 0) {
      return matchesValues(raw, filter.values, filter.valuesOperator ?? 'OR');
    }
    if (filter.text && filter.text.trim() !== '') {
      const text = filter.text.trim().toLowerCase();
      return toCompareValues(raw).some((v) => v.toLowerCase().includes(text));
    }
    return true;
  }

  if (filter.text !== undefined && filter.text.trim() !== '') {
    const text = filter.text.trim().toLowerCase();
    return String(raw ?? '').toLowerCase().includes(text);
  }

  return true;
}

export function matchesFilters(
  row: Record<string, unknown>,
  columns: CrudColumn[],
  filters: Record<string, ColumnFilter>,
  globalOperator: GlobalOperator = 'OR',
): boolean {
  const active = columns.filter((col) => isColumnFilterActive(filters[col.field]));
  if (active.length === 0) return true;
  const results = active.map((col) => columnMatches(row, col, filters[col.field]!));
  return globalOperator === 'AND' ? results.every(Boolean) : results.some(Boolean);
}
