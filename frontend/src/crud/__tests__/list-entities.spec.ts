import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { listEntities } from '../list-entities';

const SDL = `
  type Status { id: ID!, nombre: String! }
  type PageInfo { hasNextPage: Boolean!, hasPreviousPage: Boolean! }
  type StatusEdge { cursor: String!, node: Status }
  type StatusConnection { edges: [StatusEdge], pageInfo: PageInfo!, totalCount: Int }

  type Piloto { id: ID!, nombre: String! }
  type PilotoPageConnection { collection: [Piloto!]!, paginationInfo: PaginationInfo! }
  type PaginationInfo { totalCount: Int!, itemsPerPage: Int!, lastPage: Int! }

  type Empresa { id: ID!, nombre: String! }

  type Factura { id: ID!, total: Float! }
  type FacturaEdge { cursor: String!, node: Factura }
  type FacturaConnection { edges: [FacturaEdge], pageInfo: PageInfo!, totalCount: Int }

  type Node { id: ID! }

  type Query {
    status(id: ID!): Status
    statuses(first: Int, after: String): StatusConnection
    piloto(id: ID!): Piloto
    pilotos(page: Int, itemsPerPage: Int): PilotoPageConnection
    empresas: [Empresa!]!
    facturas: FacturaConnection
    facturasDelMes: FacturaConnection
    node(id: ID!): Node
    nodes: [Node!]!
  }
`;

describe('listEntities', () => {
  const schema = buildSchema(SDL);

  it('enumera entidades listables detectando cada forma de paginación', () => {
    const byType = new Map(listEntities(schema).map((e) => [e.typeName, e]));

    expect(byType.get('Status')).toEqual({ typeName: 'Status', collectionField: 'statuses', shape: 'relay' });
    expect(byType.get('Piloto')).toEqual({ typeName: 'Piloto', collectionField: 'pilotos', shape: 'page' });
    expect(byType.get('Empresa')).toEqual({ typeName: 'Empresa', collectionField: 'empresas', shape: 'flat' });
    expect(byType.get('Factura')).toEqual({ typeName: 'Factura', collectionField: 'facturas', shape: 'relay' });
  });

  it('descarta queries de ítem y tipos wrapper', () => {
    const typeNames = listEntities(schema).map((e) => e.typeName);
    expect(typeNames).not.toContain('StatusConnection');
    expect(typeNames).not.toContain('FacturaEdge');
    expect(typeNames).not.toContain('PageInfo');
    expect(typeNames).not.toContain('PaginationInfo');
    expect(typeNames).not.toContain('Node');
  });

  it('deduplica entidades con queries custom de colección', () => {
    const facturas = listEntities(schema).filter((e) => e.typeName === 'Factura');
    expect(facturas).toHaveLength(1);
    expect(facturas[0]?.collectionField).toBe('facturas');
  });

  it('ordena por nombre de tipo', () => {
    const names = listEntities(schema).map((e) => e.typeName);
    expect(names).toEqual([...names].sort());
  });
});
