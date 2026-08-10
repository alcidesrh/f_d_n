import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { buildColumns, humanize, scalarKind, getNodeTypeName } from '../entity-meta';

const SDL = `
  enum EstadoCivil { SOLTERO, CASADO }

  type Empresa { id: ID!, nombre: String! }

  type Status { id: ID!, nombre: String! }

  type Piloto {
    id: ID!
    nombre: String!
    apellido: String
    email: String
    edad: Int!
    salario: Float
    activo: Boolean
    nacimiento: DateTime
    estadoCivil: EstadoCivil
    empresa: Empresa
    status: Status
    trayectos: [Trayecto!]!
    plainPassword: String
    token: String
    _id: Int!
  }

  type Trayecto { id: ID! }

  type PaginationInfo { totalCount: Int! }
  type PilotoPageConnection { collection: [Piloto!]!, paginationInfo: PaginationInfo! }

  scalar DateTime

  type Query {
    piloto(id: ID!): Piloto
    pilotos(page: Int, itemsPerPage: Int): PilotoPageConnection
  }
`;

describe('entity-meta', () => {
  const schema = buildSchema(SDL);

  it('humaniza nombres de campo camelCase y snake_case', () => {
    expect(humanize('itemsPerPage')).toBe('Items Per Page');
    expect(humanize('created_at')).toBe('Created At');
    expect(humanize('nombre')).toBe('Nombre');
  });

  it('mapea scalars a ColumnKind', () => {
    expect(scalarKind('String')).toBe('string');
    expect(scalarKind('ID')).toBe('string');
    expect(scalarKind('Int')).toBe('number');
    expect(scalarKind('Float')).toBe('number');
    expect(scalarKind('Decimal')).toBe('number');
    expect(scalarKind('Boolean')).toBe('boolean');
    expect(scalarKind('DateTime')).toBe('date');
  });

  it('construye columnas con kinds, exclusions y sortable', () => {
    const columns = buildColumns(schema, 'Piloto');
    const byField = new Map(columns.map((c) => [c.field, c]));

    expect(byField.get('nombre')).toMatchObject({ kind: 'string', isRelation: false, sortable: true, filterable: true });
    expect(byField.get('edad')).toMatchObject({ kind: 'number' });
    expect(byField.get('salario')).toMatchObject({ kind: 'number' });
    expect(byField.get('activo')).toMatchObject({ kind: 'boolean' });
    expect(byField.get('nacimiento')).toMatchObject({ kind: 'date' });
    expect(byField.get('estadoCivil')).toMatchObject({ kind: 'enum', isRelation: false, sortable: true });
    expect(byField.get('empresa')).toMatchObject({ kind: 'relation', isRelation: true, typeName: 'Empresa', sortable: false });
    expect(byField.get('status')).toMatchObject({ kind: 'relation', isRelation: true, typeName: 'Status', filterable: true });
    expect(byField.get('id')).toBeDefined();
  });

  it('excluye sub-colecciones, campos sensibles y _id', () => {
    const columns = buildColumns(schema, 'Piloto');
    const fields = columns.map((c) => c.field);
    expect(fields).not.toContain('trayectos');
    expect(fields).not.toContain('plainPassword');
    expect(fields).not.toContain('token');
    expect(fields).not.toContain('_id');
  });

  it('resuelve el node type de la colección', () => {
    expect(getNodeTypeName(schema, 'Piloto')).toBe('Piloto');
  });
});
