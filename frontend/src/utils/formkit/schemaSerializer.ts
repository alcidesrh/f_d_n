/**
 * Serializador puro: EntitySchema (metadata aplanada de API Platform) →
 * FormKit Schema en sintaxis corta (`{ $formkit: '<CustomInput>', ... }`).
 *
 * Los nodos resultantes son JSON-plano (sin funciones), listos para
 * `<FormKit type="form" :schema="...">`. Las opciones de relaciones se
 * inyectan desde fuera (`relationOptions`) porque requieren fetch previo de
 * `registry.getEntity(target).loadFullList()`.
 */

import type { FormKitSchemaNode } from "@formkit/core";
import type { AgnosticOption } from "@/lib/apollo/types";

/** Shape común de `EntityFieldSchema` y `SchemaInputField` (lo que pide el form). */
export interface FormFieldSource {
  name: string;
  namedType: string;
  kind: string;
  required: boolean;
  isList: boolean;
  isRelation: boolean;
  enumValues: string[];
}

export interface SerializeFormOptions {
  /** `create` oculta `id`; `update` lo muestra deshabilitado. */
  mode?: "create" | "update";
  /** Labels por campo; por defecto se humaniza el nombre (`createdAt` → `Created At`). */
  labels?: Record<string, string>;
  /** Options por campo de relación (de `loadFullList`): `{ id: IRI, label }`. */
  relationOptions?: Record<string, AgnosticOption[]>;
  /** Valores iniciales por campo (ya hidratados, ver `hydrateInitialValues`). */
  values?: Record<string, unknown>;
  /** Sufijo de `key` para remontar el formulario (reset sin perder estado viejo). */
  resetKey?: string | number;
}

const PASSWORD_RE = /password|clave|contrasena|secret/i;
const TEXTAREA_RE = /descripcion|observacion|nota|comentario|contenido|texto|mensaje|bio|motivo/i;
const EMAIL_RE = /email|correo/i;
const URL_RE = /url|website|sitio/i;

export function humanizeLabel(name: string): string {
  return name
    .replace(/^_+/, "")
    .replace(/[_-]+(.)/g, (_, c: string) => ` ${c.toUpperCase()}`)
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/^\w/, (c) => c.toUpperCase());
}

/** Capitaliza la primera letra de un label (deja el resto intacto: `CUIT`, `e-mail`…). */
export function capitalizeLabel(label: string): string {
  return label.replace(/^\w/, (c) => c.toUpperCase());
}

/** Nombre del Custom Input registrado en `formkit.config.ts` para el campo. */
export function inferInputType(entry: FormFieldSource): string {
  if (entry.isRelation) return entry.isList ? "MultiSelect" : "Select";
  if (entry.kind === "ENUM") return "Select";
  if (entry.namedType === "Boolean") return "ToggleSwitch";
  if (entry.namedType === "Date" || entry.namedType === "DateTime") return "DatePicker";
  if (entry.namedType === "Int" || entry.namedType === "Float") return "InputNumber";
  if (PASSWORD_RE.test(entry.name)) return "Password";
  if (TEXTAREA_RE.test(entry.name)) return "TextArea";
  return "InputText";
}

/** Options de Select/MultiSelect: relaciones de `fullList` (`{ id: IRI }` → `value`), enums de sus valores. */
export function buildFieldOptions(
  entry: FormFieldSource,
  fullList: AgnosticOption[],
): Array<{ label: string; value: string }> {
  if (entry.isRelation)
    return fullList.map((option) => ({ label: option.label, value: option.id }));
  return entry.enumValues.map((value) => ({ label: capitalizeLabel(value), value }));
}

/** Validaciones FormKit según nulabilidad/tipo y heurísticas de nombre. */
export function validationFor(entry: FormFieldSource): string | undefined {
  const rules: string[] = [];
  if (entry.required) rules.push("required");
  if (entry.namedType === "Int" || entry.namedType === "Float") rules.push("number");
  if (EMAIL_RE.test(entry.name)) rules.push("email");
  if (URL_RE.test(entry.name)) rules.push("url");
  return rules.length > 0 ? rules.join("|") : undefined;
}

function isFullWidth(entry: FormFieldSource): boolean {
  return TEXTAREA_RE.test(entry.name);
}

/** `YYYY-MM-DD` local (no UTC, evita saltos de día en zonas positivas). */
function toDateOnly(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function relationId(entry: unknown): unknown {
  if (entry === null || entry === undefined) return null;
  if (typeof entry === "string" || typeof entry === "number") return entry;
  if (typeof entry === "object") {
    const record = entry as Record<string, unknown>;
    return record["@id"] ?? record.id ?? null;
  }
  return entry;
}

function relationValue(raw: unknown, isList: boolean): unknown {
  if (isList && Array.isArray(raw)) {
    return raw
      .map((item) => relationId(item))
      .filter((value) => value !== null && value !== undefined);
  }
  return relationId(raw);
}

/**
 * Hidrata datos iniciales (item de una query, relaciones como `{ id, label }`)
 * a valores de formulario: relaciones → IRI/ids, fechas string → `Date` local.
 */
export function hydrateInitialValues(
  fields: FormFieldSource[],
  item: Record<string, unknown> | null | undefined,
  mode: "create" | "update" = "create",
): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  if (!item) return out;
  const byName = new Map(fields.map((field) => [field.name, field]));
  for (const [key, raw] of Object.entries(item)) {
    if (key === "clientMutationId") continue;
    if (key === "id" && mode === "create") continue;
    const field = byName.get(key);
    if (!field) continue;
    if (field.isRelation) {
      out[key] = relationValue(raw, field.isList);
      continue;
    }
    if (/date/i.test(field.namedType) && typeof raw === "string") {
      const date = new Date(`${raw.slice(0, 10)}T00:00:00`);
      if (!Number.isNaN(date.getTime())) out[key] = date;
      continue;
    }
    out[key] = raw;
  }
  return out;
}

/**
 * Normaliza valores de formulario antes del submit: fechas (Date o ISO) a
 * `YYYY-MM-DD` (el backend rechaza datetime completo, ver List.vue). Las
 * relaciones ya viajan como IRI/ids (options de los selects).
 */
export function serializeSubmitValue(
  fields: FormFieldSource[],
  data: Record<string, unknown>,
): Record<string, unknown> {
  const byName = new Map(fields.map((field) => [field.name, field]));
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data)) {
    const field = byName.get(key);
    if (field && /date/i.test(field.namedType)) {
      if (value instanceof Date && !Number.isNaN(value.getTime())) {
        out[key] = toDateOnly(value);
      } else if (typeof value === "string") {
        out[key] = value.slice(0, 10);
      } else {
        out[key] = value;
      }
      continue;
    }
    out[key] = value;
  }
  return out;
}

function buildFieldNode(
  entityName: string,
  entry: FormFieldSource,
  opts: Required<Pick<SerializeFormOptions, "mode">> & SerializeFormOptions,
): FormKitSchemaNode {
  const name = entry.name;
  const inputType = inferInputType(entry);
  const node: Record<string, unknown> = {
    key: `${entityName}.${name}${opts.resetKey !== undefined ? `_${String(opts.resetKey)}` : ""}`,
    $formkit: inputType,
    name,
    label: capitalizeLabel(opts.labels?.[name] ?? humanizeLabel(name)),
  };
  const validation = validationFor(entry);
  if (validation) node.validation = validation;
  const hydrated = opts.values?.[name];
  if (hydrated !== undefined && hydrated !== null) node.value = hydrated;
  const isId = name === "id" || name === "_id";
  switch (inputType) {
    case "Select":
      ((node.options = opts.relationOptions?.[name]), //buildFieldOptions(entry, opts.relationOptions?.[name] ?? []);
        (node.filter = true));
      node.showClear = true;
      node.placeholder = "Selecciona…";
      node.optionLabel = "label";
      node.optionValue = "value";

      break;
    case "MultiSelect":
      ((node.options = opts.relationOptions?.[name]), //buildFieldOptions(entry, opts.relationOptions?.[name] ?? []);
        (node.filter = true));
      node.display = "chip";
      node.placeholder = "Selecciona…";
      node.optionLabel = "label";
      node.optionValue = "value";
      node.showClear = true;
      break;
    case "DatePicker":
      node.dateFormat = "dd/mm/yy";
      node.showIcon = true;
      node.showClear = true;
      break;
    case "InputNumber":
      if (entry.namedType === "Int") {
        node.minFractionDigits = 0;
        node.maxFractionDigits = 0;
      }
      break;
    case "Password":
      node.toggleMask = true;
      node.feedback = false;
      break;
    case "TextArea":
      node.autoResize = true;
      node.rows = 3;
      break;
    case "InputText":
      if (isId && opts.mode === "update") node.disabled = true;
      break;
  }
  return node as FormKitSchemaNode;
}

/** Envuelve los campos en filas de grid responsive (2 columnas en md+, TextArea a ancho completo). */
function layoutNodes(nodes: FormKitSchemaNode[]): FormKitSchemaNode[] {
  const rows: FormKitSchemaNode[] = [];
  let row: FormKitSchemaNode[] = [];
  const flush = () => {
    if (row.length === 0) return;
    rows.push({
      $el: "div",
      attrs: { class: "grid grid-cols-1 md:grid-cols-2 gap-x-6" },
      children: row,
    } as FormKitSchemaNode);
    row = [];
  };
  for (const node of nodes) {
    row.push(node);
    if (row.length === 2) flush();
  }
  flush();
  return rows;
}

/**
 * Serializa los campos a FormKit Schema en sintaxis corta. Los nodos son
 * JSON-plano: `JSON.parse(JSON.stringify(schema))` los reproduce tal cual.
 */
export function serializeEntityForm(
  entityName: string,
  fields: FormFieldSource[],
  opts: SerializeFormOptions = {},
): FormKitSchemaNode[] {
  const mode = opts.mode ?? "create";
  const nodes: FormKitSchemaNode[] = [];
  for (const entry of fields) {
    const node = buildFieldNode(entityName, entry, { ...opts, mode });
    if (isFullWidth(entry)) {
      nodes.push({
        $el: "div",
        attrs: { class: "md:col-span-2" },
        children: [node],
      } as FormKitSchemaNode);
    } else {
      nodes.push(node);
    }
  }

  return layoutNodes(nodes);
}
