import type { CollectionShape } from '@graphql-orm/core';

export type ColumnKind = 'string' | 'number' | 'boolean' | 'date' | 'enum' | 'relation';

export interface CrudColumn {
  field: string;
  label: string;
  kind: ColumnKind;
  isList: boolean;
  isRelation: boolean;
  sortable: boolean;
  filterable: boolean;
  typeName: string;
}

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

export type FormMode = 'create' | 'update';

export interface CrudEntityInfo {
  /** Nombre del tipo GraphQL (p. ej. "Status") — clave para repository/descriptor. */
  typeName: string;
  /** Campo de Query que lista la colección (p. ej. "statuses"). */
  collectionField: string;
  /** Forma de paginación real detectada en el schema. */
  shape: CollectionShape;
}

export interface RelationOption {
  label: string;
  value: string | number | boolean;
}