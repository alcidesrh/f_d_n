/**
 * Helpers puros del listado agnóstico (`List.vue`), sin dependencia de DOM:
 * etiquetado de celdas (relaciones OneToMany/ManyToMany), detección de tipo
 * de columna, resolución de argumentos de filtro del schema y normalización
 * de rangos de fecha del DatePicker.
 */

import { presenterFor } from "@/core/entities/columnPresenters";
import type { EntitySchema, OrderCondition } from "@/core/graphql/types";

/** Prioridad de propiedades para etiquetar un objeto relación. */
const LABEL_PROPS = ["name", "label", "id"] as const;

export type FilterFieldKind = "date" | "relation" | "number" | "boolean" | "text";

/** Etiqueta de una celda: primitivo tal cual, objeto por `name`/`label`/`id`. */
export function cellLabel(value: unknown): string {
  if (value === null || value === undefined) return "";
  if (Array.isArray(value)) {
    return value
      .map((entry) => cellLabel(entry))
      .filter((label) => label !== "")
      .join(", ");
  }
  if (typeof value === "object") {
    const record = value as Record<string, unknown>;
    for (const prop of LABEL_PROPS) {
      const candidate = record[prop];
      if (candidate !== null && candidate !== undefined) return String(candidate);
    }
    return "";
  }
  return String(value);
}

/** Valor de display de `item[field]` (puede ser relación u objeto con nombre). */
export function cellValue(item: unknown, field: string): string {
  const record = (item ?? {}) as Record<string, unknown>;
  return cellLabel(record[field]);
}

/** Extrae el sufijo numérico de un IRI de resource (`/api/icons/1` → `1`). */
export function idDisplay(value: unknown): string {
  const label = cellLabel(value);
  const match = label.match(/\/(\d+)$/);
  return match ? (match[1] ?? label) : label;
}

const pad = (n: number) => String(n).padStart(2, "0");

/** `hh:mm am|pm` (12 h) de un datetime. */
function formatHour(date: Date): string {
  const h = date.getHours();
  return `${pad(h % 12 || 12)}:${pad(date.getMinutes())} ${h < 12 ? "am" : "pm"}`;
}

/** `hour` → `hh:mm am|pm`; `date` → `dd/mm/yyyy hh:mm am|pm`. Ambos llegan como datetime. */
function formatDateTimeKind(label: string, kind: "hour" | "date"): string {
  const date = new Date(label);
  if (!label || Number.isNaN(date.getTime())) return label;
  if (kind === "hour") return formatHour(date);
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${formatHour(date)}`;
}

/** Texto del presentador de `Entidad.campo` (undefined si no hay o la relación está vacía). */
function presentedText(item: unknown, field: string, entityName?: string): string | undefined {
  const presenter = presenterFor(entityName, field);
  const value = (item as Record<string, unknown> | null)?.[field];
  if (!presenter || value == null || typeof value !== "object") return undefined;
  return Array.isArray(value) ? value.map((entry) => presenter.text(entry)).join(", ") : presenter.text(value as Record<string, any>);
}

/** Valor de display de una celda; los campos `id`/`_id` muestran el número, no el IRI. */
export function cellDisplay(item: unknown, column: { field: string; kind?: string }, entityName?: string): string {
  const presented = presentedText(item, column.field, entityName);
  if (presented !== undefined) return presented;
  const label = cellValue(item, column.field);
  if (column.field === "id" || column.field === "_id") return idDisplay(label);
  if (column.kind === "hour" || column.kind === "date") return formatDateTimeKind(label, column.kind);
  return label;
}

/** Tipo de columna según el schema (para elegir input de filtro y comparar). */
export function fieldKind(entity: EntitySchema, field: string): FilterFieldKind {
  const entry = entity.fields.find((f) => f.name === field);
  if (!entry) return "text";
  if (entry.isRelation) return "relation";
  if (entry.namedType === "Date" || entry.namedType === "DateTime") return "date";
  if (entry.namedType === "Int" || entry.namedType === "Float") return "number";
  if (entry.namedType === "Boolean") return "boolean";
  return "text";
}

export interface FilterArgMatch {
  /** Arg de colección que recibe el valor único (null si no hay). */
  single: string | null;
  /** Arg `{campo}_after` para rangos de fecha (null si no hay). */
  after: string | null;
  /** Arg `{campo}_before` para rangos de fecha (null si no hay). */
  before: string | null;
}

export function noServerFilter(match: FilterArgMatch): boolean {
  return match.single === null && match.after === null && match.before === null;
}

/**
 * Resuelve los argumentos de filtro de la colección que matchean la columna:
 * arg exacto con el nombre del campo, `{campo}_after`/`{campo}_before` para
 * fechas y `{campo}_contains` para strings. Sin match → la columna no se filtra.
 */
export function resolveFilterArgs(entity: EntitySchema, field: string): FilterArgMatch {
  const kind = fieldKind(entity, field);
  const byName = (name: string) => entity.filterArgs.find((arg) => arg.name === name);
  if (byName(field)) return { single: field, after: null, before: null };
  if (kind === "date") {
    const after = byName(`${field}_after`) ? `${field}_after` : null;
    const before = byName(`${field}_before`) ? `${field}_before` : null;
    if (after || before) return { single: null, after, before };
  }
  const contains = byName(`${field}_contains`) ? `${field}_contains` : null;
  if (contains) return { single: contains, after: null, before: null };
  return { single: null, after: null, before: null };
}

export interface DateRangeFilter {
  after?: string;
  before?: string;
}

function toIso(value: unknown): string | undefined {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toISOString().slice(0, 10);
  }
  if (typeof value === "string" && value.length >= 10) return value.slice(0, 10);
  return undefined;
}

/**
 * Normaliza el valor del DatePicker en modo rango a `{ after, before }` ISO
 * (`yyyy-mm-dd`). Soporta tanto `[Date, Date]` como `{ start, end }`.
 */
export function rangeToIso(range: unknown): DateRangeFilter {
  if (Array.isArray(range) && range.length >= 2) {
    return { after: toIso(range[0]), before: toIso(range[1]) };
  }
  if (range && typeof range === "object") {
    const record = range as { start?: unknown; end?: unknown };
    return { after: toIso(record.start), before: toIso(record.end) };
  }
  return {};
}

/** True si el valor de un filtro está "vacío" (no filtra). */
export function isEmptyFilterValue(value: unknown): boolean {
  if (value === undefined || value === null || value === "") return true;
  if (Array.isArray(value)) return value.length === 0;
  return false;
}

/** ¿La colección acepta un argumento para filtrar este campo en la base de datos? */
export function hasServerFilter(entity: EntitySchema, field: string): boolean {
  return !noServerFilter(resolveFilterArgs(entity, field));
}

function toServerScalar(value: unknown, kind: FilterFieldKind): unknown {
  if (kind === "boolean") return value === true || value === "true";
  if (kind === "number") {
    const num = Number(value);
    return Number.isNaN(num) ? value : num;
  }
  return value;
}

/** Filtros de la UI (campo → valor) → argumentos de la colección GraphQL. */
export function toServerFilters(entity: EntitySchema, filters: Record<string, unknown>): Record<string, unknown> {
  const server: Record<string, unknown> = {};
  for (const [field, value] of Object.entries(filters)) {
    if (isEmptyFilterValue(value)) continue;
    const kind = fieldKind(entity, field);
    const args = resolveFilterArgs(entity, field);
    if (kind === "date") {
      const { after, before } = rangeToIso(value);
      if (args.after && after) server[args.after] = after;
      if (args.before && before) server[args.before] = before;
    } else if (args.single) {
      server[args.single] = toServerScalar(value, kind);
    }
  }
  return server;
}

/** Inversa de `toServerFilters`: argumentos persistidos → valores de los inputs. */
export function fromServerFilters(entity: EntitySchema, server: Record<string, unknown>): Record<string, unknown> {
  const filters: Record<string, unknown> = {};
  for (const { name: field } of entity.fields) {
    const args = resolveFilterArgs(entity, field);
    if (fieldKind(entity, field) === "date") {
      const after = args.after ? server[args.after] : undefined;
      const before = args.before ? server[args.before] : undefined;
      if (typeof after === "string" && typeof before === "string") filters[field] = [new Date(after), new Date(before)];
    } else if (args.single && server[args.single] !== undefined) {
      filters[field] = server[args.single];
    }
  }
  return filters;
}

/**
 * Valor editado en línea → input GraphQL: fechas a `YYYY-MM-DD` (el backend
 * rechaza datetime completo) y relaciones a su IRI.
 */
export function toEditedInput(entity: EntitySchema, field: string, value: unknown): unknown {
  const entry = entity.fields.find((f) => f.name === field);
  if (entry?.isRelation && value && typeof value === "object") {
    const record = value as Record<string, unknown>;
    return record.value ?? record.id ?? null;
  }
  if (entry && /date/i.test(entry.namedType)) {
    if (value instanceof Date) return value.toISOString().slice(0, 10);
    if (typeof value === "string") return value.slice(0, 10);
  }
  return value;
}

export type SortDirection = "asc" | "desc" | null;

export function sortDirection(order: OrderCondition[], field: string): SortDirection {
  const direction = order[0]?.[field];
  return direction === "ASC" ? "asc" : direction === "DESC" ? "desc" : null;
}

/** Ciclo de orden de una columna: sin orden → ASC → DESC → sin orden. */
export function nextOrder(order: OrderCondition[], field: string): OrderCondition[] {
  const current = sortDirection(order, field);
  if (current === null) return [{ [field]: "ASC" }];
  if (current === "asc") return [{ [field]: "DESC" }];
  return [];
}
