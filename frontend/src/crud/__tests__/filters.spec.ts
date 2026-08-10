import { describe, it, expect } from 'vitest';
import { matchesFilters, matchesValues, isColumnFilterActive } from '../filters';
import type { CrudColumn } from '../entity-meta';

const columns: CrudColumn[] = [
  { field: 'nombre', label: 'Nombre', kind: 'string', isList: false, isRelation: false, sortable: true, filterable: true, typeName: 'String' },
  { field: 'apellido', label: 'Apellido', kind: 'string', isList: false, isRelation: false, sortable: true, filterable: true, typeName: 'String' },
  { field: 'activo', label: 'Activo', kind: 'boolean', isList: false, isRelation: false, sortable: true, filterable: true, typeName: 'Boolean' },
  { field: 'estadoCivil', label: 'Estado Civil', kind: 'enum', isList: false, isRelation: false, sortable: true, filterable: true, typeName: 'EstadoCivil' },
  { field: 'empresa', label: 'Empresa', kind: 'relation', isList: false, isRelation: true, sortable: false, filterable: true, typeName: 'Empresa' },
  { field: 'nacimiento', label: 'Nacimiento', kind: 'date', isList: false, isRelation: false, sortable: true, filterable: true, typeName: 'DateTime' },
];

const rows = [
  {
    id: '/api/pilotos/1',
    nombre: 'Juan',
    apellido: 'Pérez',
    activo: true,
    estadoCivil: 'SOLTERO',
    empresa: { id: '/api/empresas/1', nombre: 'Andén SA' },
    nacimiento: '1990-05-10T00:00:00+00:00',
  },
  {
    id: '/api/pilotos/2',
    nombre: 'María',
    apellido: 'Gómez',
    activo: false,
    estadoCivil: 'CASADO',
    empresa: { id: '/api/empresas/2', nombre: 'Vía Norte' },
    nacimiento: '1985-01-20T00:00:00+00:00',
  },
];

describe('filters', () => {
  it('sin filtros activos devuelve todo', () => {
    expect(matchesFilters(rows[0]!, columns, {})).toBe(true);
  });

  it('filtro de texto contiene, case-insensitive', () => {
    expect(matchesFilters(rows[0]!, columns, { nombre: { text: 'juan' } }, 'AND')).toBe(true);
    expect(matchesFilters(rows[1]!, columns, { nombre: { text: 'JUAN' } }, 'AND')).toBe(false);
  });

  it('multi-select de relación OR', () => {
    const filter = { empresa: { values: ['/api/empresas/1'], valuesOperator: 'OR' as const } };
    expect(matchesFilters(rows[0]!, columns, filter, 'AND')).toBe(true);
    expect(matchesFilters(rows[1]!, columns, filter, 'AND')).toBe(false);
  });

  it('multi-select de relación AND requiere todos los valores', () => {
    const filter = { empresa: { values: ['/api/empresas/1', '/api/empresas/2'], valuesOperator: 'AND' as const } };
    expect(matchesFilters(rows[0]!, columns, filter, 'AND')).toBe(false);
  });

  it('filtro de enum por valores', () => {
    const filter = { estadoCivil: { values: ['CASADO'] } };
    expect(matchesFilters(rows[1]!, columns, filter, 'AND')).toBe(true);
    expect(matchesFilters(rows[0]!, columns, filter, 'AND')).toBe(false);
  });

  it('rango de fechas from/to', () => {
    expect(matchesFilters(rows[0]!, columns, { nacimiento: { from: '1990-01-01' } }, 'AND')).toBe(true);
    expect(matchesFilters(rows[1]!, columns, { nacimiento: { from: '1990-01-01' } }, 'AND')).toBe(false);
    expect(matchesFilters(rows[0]!, columns, { nacimiento: { to: '1990-05-10' } }, 'AND')).toBe(true);
    expect(matchesFilters(rows[0]!, columns, { nacimiento: { from: '1991-01-01', to: '1999-12-31' } }, 'AND')).toBe(false);
  });

  it('operador global AND requiere todas las columnas', () => {
    const filters = { nombre: { text: 'a' }, estadoCivil: { values: ['SOLTERO'] } };
    expect(matchesFilters(rows[0]!, columns, filters, 'AND')).toBe(true);
    expect(matchesFilters(rows[1]!, columns, filters, 'AND')).toBe(false);
  });

  it('operador global OR acepta cualquier columna', () => {
    const filters = { nombre: { text: 'zzz' }, estadoCivil: { values: ['SOLTERO'] } };
    expect(matchesFilters(rows[0]!, columns, filters, 'OR')).toBe(true);
  });

  it('matchesValues sobre arrays y strings planos', () => {
    expect(matchesValues('/api/empresas/1', ['/api/empresas/1'], 'OR')).toBe(true);
    expect(matchesValues(['a', 'b'], ['b'], 'OR')).toBe(true);
    expect(matchesValues(['a'], ['a', 'b'], 'AND')).toBe(false);
  });

  it('isColumnFilterActive ignora filtros vacíos', () => {
    expect(isColumnFilterActive(undefined)).toBe(false);
    expect(isColumnFilterActive({})).toBe(false);
    expect(isColumnFilterActive({ text: '   ' })).toBe(false);
    expect(isColumnFilterActive({ values: [] })).toBe(false);
    expect(isColumnFilterActive({ text: 'x' })).toBe(true);
  });
});
