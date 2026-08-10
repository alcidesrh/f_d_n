import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { buildEntityDescriptor } from '../src/schema/entity-descriptor';

describe('Collection Shape Detection', () => {
  it('detects Relay connection collection shape (edges + pageInfo)', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      type BookEdge { cursor: String!, node: Book }
      type PageInfo { startCursor: String, endCursor: String, hasNextPage: Boolean!, hasPreviousPage: Boolean! }
      type BookConnection { edges: [BookEdge], pageInfo: PageInfo!, totalCount: Int }
      type Query {
        book(id: ID!): Book
        books(first: Int, after: String): BookConnection
      }
    `;
    const schema = buildSchema(sdl);
    const desc = buildEntityDescriptor(schema, 'Book');
    expect(desc.queries.collection?.collectionShape).toBe('relay');
    expect(desc.queries.collection?.nodeTypeName).toBe('Book');
  });

  it('detects Page connection collection shape (collection + paginationInfo)', () => {
    const sdl = `
      type Author { id: ID!, name: String! }
      type PaginationInfo { itemsPerPage: Int!, lastPage: Int!, totalCount: Int!, hasNextPage: Boolean! }
      type AuthorPage { collection: [Author!]!, paginationInfo: PaginationInfo! }
      type Query {
        author(id: ID!): Author
        authors(page: Int, itemsPerPage: Int): AuthorPage
      }
    `;
    const schema = buildSchema(sdl);
    const desc = buildEntityDescriptor(schema, 'Author');
    expect(desc.queries.collection?.collectionShape).toBe('page');
    expect(desc.queries.collection?.nodeTypeName).toBe('Author');
  });

  it('detects Flat list collection shape (array of items directly)', () => {
    const sdl = `
      type Status { id: ID!, name: String! }
      type Query {
        status(id: ID!): Status
        statuss: [Status!]!
      }
    `;
    const schema = buildSchema(sdl);
    const desc = buildEntityDescriptor(schema, 'Status');
    expect(desc.queries.collection?.collectionShape).toBe('flat');
  });

  it('resolves distinct return types per operation (serialization groups)', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      type BookItem { id: ID!, title: String! }
      type BookCollection { id: ID!, title: String!, isbn: String }
      type BookEdge { cursor: String!, node: BookCollection }
      type PageInfo { startCursor: String, endCursor: String, hasNextPage: Boolean!, hasPreviousPage: Boolean! }
      type BookConnection { edges: [BookEdge], pageInfo: PageInfo!, totalCount: Int }
      type Query {
        book(id: ID!): BookItem
        books(first: Int): BookConnection
      }
    `;
    const schema = buildSchema(sdl);
    const desc = buildEntityDescriptor(schema, 'Book');

    // el ítem NO es el mismo tipo que el nodo de colección
    expect(desc.queries.item?.returnTypeName).toBe('BookItem');
    expect(desc.queries.collection?.returnTypeName).toBe('BookConnection');
    expect(desc.queries.collection?.nodeTypeName).toBe('BookCollection');
    expect(desc.queries.collection?.nodeTypeName).not.toBe(desc.queries.item?.returnTypeName);
    expect(desc.queries.item?.returnFields.map((f) => f.name)).not.toContain('isbn');
  });

  it('detects payload entity field inside mutation payloads', () => {
    const sdl = `
      type Status { id: ID!, name: String! }
      input createStatusInput { name: String! }
      type createStatusPayload { status: Status, clientMutationId: String }
      type Query { status(id: ID!): Status }
      type Mutation { createStatus(input: createStatusInput!): createStatusPayload }
    `;
    const schema = buildSchema(sdl);
    const desc = buildEntityDescriptor(schema, 'Status');
    expect(desc.mutations.create?.payloadEntityField).toBe('status');
    expect(desc.mutations.create?.inputTypeName).toBe('createStatusInput');
  });
});
