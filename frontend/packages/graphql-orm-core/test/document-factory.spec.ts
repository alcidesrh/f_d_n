import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { SchemaRegistry } from '../src/schema/schema-registry';
import {
  buildItemQuery,
  buildCollectionQuery,
  buildCreateMutation,
  buildUpdateMutation,
  buildDeleteMutation,
  buildCustomOperation,
} from '../src/documents/document-factory';

const sdl = `
  type Book { id: ID!, title: String!, author: Author }
  type Author { id: ID!, name: String! }
  type BookEdge { cursor: String!, node: Book }
  type PageInfo { startCursor: String, endCursor: String, hasNextPage: Boolean!, hasPreviousPage: Boolean! }
  type BookConnection { edges: [BookEdge], pageInfo: PageInfo!, totalCount: Int }

  input createBookInput { title: String!, author: String, clientMutationId: String }
  type createBookPayload { book: Book, clientMutationId: String }
  input updateBookInput { id: ID!, title: String, clientMutationId: String }
  type updateBookPayload { book: Book, clientMutationId: String }
  input deleteBookInput { id: ID!, clientMutationId: String }
  type deleteBookPayload { book: Book, clientMutationId: String }

  type Query {
    book(id: ID!): Book
    books(first: Int, after: String): BookConnection
    featuredBook(id: ID!): Book
  }

  type Mutation {
    createBook(input: createBookInput!): createBookPayload
    updateBook(input: updateBookInput!): updateBookPayload
    deleteBook(input: deleteBookInput!): deleteBookPayload
  }
`;

describe('DocumentFactory', () => {
  const schema = buildSchema(sdl);
  const registry = new SchemaRegistry(schema);

  it('builds item query correctly', () => {
    const { document } = buildItemQuery(registry, 'Book');
    expect(document).toContain('query bookItem($id: ID!)');
    expect(document).toContain('book(id: $id)');
    expect(document).toContain('title');
  });

  it('builds collection query with depth control', () => {
    const { document } = buildCollectionQuery(registry, 'Book');
    expect(document).toContain('query booksCollection($first: Int, $after: String)');
    expect(document).toContain('edges { cursor node {');
    expect(document).toContain('author {');
  });

  it('builds create mutation correctly', () => {
    const { document } = buildCreateMutation(registry, 'Book');
    expect(document).toContain('mutation createBook($input: createBookInput!)');
    expect(document).toContain('createBook(input: $input)');
    expect(document).toContain('book {');
  });

  it('builds update mutation merging the payload entity field', () => {
    const { document } = buildUpdateMutation(registry, 'Book');
    expect(document).toContain('mutation updateBook($input: updateBookInput!)');
    expect(document).toContain('updateBook(input: $input)');
    expect(document).toContain('book {');
  });

  it('builds delete mutation selecting only the entity id', () => {
    const { document } = buildDeleteMutation(registry, 'Book');
    expect(document).toContain('mutation deleteBook($input: deleteBookInput!)');
    expect(document).toContain('deleteBook(input: $input)');
    expect(document).toContain('book { id }');
  });

  it('builds custom operations as escape hatch', () => {
    const { document } = buildCustomOperation(registry, 'Book', 'featuredBook');
    expect(document).toContain('query featuredBook($id: ID!)');
    expect(document).toContain('featuredBook(id: $id)');
    expect(document).toContain('title');
  });

  it('throws when building a mutation the schema does not define', () => {
    expect(() => buildDeleteMutation(registry, 'Author')).toThrow(/delete/i);
  });
});
