import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { SchemaRegistry } from '../src/schema/schema-registry';
import { createRepository } from '../src/repository/create-repository';
import { ClientValidationError, GraphQLApiError } from '../src/transport/errors';
import type { GraphQLTransport } from '../../types';

const relaySdl = `
  type Book { id: ID!, title: String! }
  type BookEdge { cursor: String!, node: Book }
  type PageInfo { startCursor: String, endCursor: String, hasNextPage: Boolean!, hasPreviousPage: Boolean! }
  type BookConnection { edges: [BookEdge], pageInfo: PageInfo!, totalCount: Int }

  input BookFilter_order { title: String, id: String }
  input createBookInput { title: String!, author: String, clientMutationId: String }
  type createBookPayload { book: Book, clientMutationId: String }
  input updateBookInput { id: ID!, title: String, clientMutationId: String }
  type updateBookPayload { book: Book, clientMutationId: String }
  input deleteBookInput { id: ID!, clientMutationId: String }
  type deleteBookPayload { book: Book, clientMutationId: String }

  type Query {
    book(id: ID!): Book
    books(first: Int, after: String, order: [BookFilter_order], title: String): BookConnection
    featuredBook(id: ID!): Book
  }

  type Mutation {
    createBook(input: createBookInput!): createBookPayload
    updateBook(input: updateBookInput!): updateBookPayload
    deleteBook(input: deleteBookInput!): deleteBookPayload
  }
`;

const pageSdl = `
  type Author { id: ID!, name: String! }
  type PaginationInfo { itemsPerPage: Int!, lastPage: Int!, totalCount: Int!, hasNextPage: Boolean! }
  type AuthorPage { collection: [Author!]!, paginationInfo: PaginationInfo! }
  type Query {
    author(id: ID!): Author
    authors(page: Int, itemsPerPage: Int): AuthorPage
  }
`;

const flatSdl = `
  type Status { id: ID!, name: String! }
  type Query {
    status(id: ID!): Status
    statuss: [Status!]!
  }
`;

function makeTransport(onExecute: (document: string, variables?: Record<string, unknown>) => unknown) {
  const calls: { document: string; variables?: Record<string, unknown> }[] = [];
  const transport: GraphQLTransport = {
    execute: async (document, variables) => {
      calls.push({ document, variables });
      return onExecute(document, variables) as never;
    },
  };
  return { transport, calls };
}

describe('Repository — relay collection', () => {
  const registry = new SchemaRegistry(buildSchema(relaySdl));

  it('normalizes findAll relay shape to { items, totalCount, pageInfo }', async () => {
    const { transport, calls } = makeTransport((doc) => {
      if (doc.includes('books(')) {
        return {
          books: {
            edges: [{ cursor: 'c1', node: { id: '/books/1', title: 'A' } }],
            totalCount: 1,
            pageInfo: { startCursor: 'c1', endCursor: 'c1', hasNextPage: false, hasPreviousPage: false },
          },
        };
      }
      return {};
    });
    const repo = createRepository(registry, transport, 'Book');
    const result = await repo.findAll();

    expect(result.items).toEqual([{ id: '/books/1', title: 'A' }]);
    expect(result.totalCount).toBe(1);
    expect(result.pageInfo?.endCursor).toBe('c1');
    expect(calls[0]?.variables).toBeUndefined();
  });

  it('passes order as an ordered list, unchanged, and only declared variables', async () => {
    const { transport, calls } = makeTransport(() => ({ books: { edges: [], totalCount: 0, pageInfo: {} } }));
    const repo = createRepository(registry, transport, 'Book');

    const order: Array<Record<string, 'ASC' | 'DESC'>> = [{ title: 'ASC' }, { id: 'DESC' }];
    await repo.findAll({ first: 10, order, filters: { title: 'x' } });

    expect(calls[0]?.variables).toEqual({ first: 10, order, title: 'x' });
    expect(calls[0]?.variables?.order).toBe(order); // misma referencia: sin reordenar ni reconstruir
  });

  it('rejects unknown filters with the list of valid args (assertKnownArgs)', async () => {
    const { transport } = makeTransport(() => ({}));
    const repo = createRepository(registry, transport, 'Book');

    await expect(repo.findAll({ filters: { nonexistent: 1 } })).rejects.toThrow(/desconocido|unknown/i);
  });

  it('findById sends the id as an opaque IRI without transformation', async () => {
    const { transport, calls } = makeTransport((doc) => (doc.includes('book(') ? { book: { id: '/books/1', title: 'A' } } : {}));
    const repo = createRepository(registry, transport, 'Book');

    const item = await repo.findById('/books/1');

    expect(item).toEqual({ id: '/books/1', title: 'A' });
    expect(calls[0]?.variables).toEqual({ id: '/books/1' });
  });

  it('create validates against the InputObject and throws ClientValidationError when invalid', async () => {
    const { transport } = makeTransport(() => ({}));
    const repo = createRepository(registry, transport, 'Book');

    await expect(repo.create({})).rejects.toBeInstanceOf(ClientValidationError);
  });

  it('create sends the validated input inside the input variable', async () => {
    const { transport, calls } = makeTransport((doc) =>
      doc.includes('createBook') ? { createBook: { book: { id: '/books/9', title: 'A' }, clientMutationId: null } } : {},
    );
    const repo = createRepository(registry, transport, 'Book');

    const created = await repo.create({ title: 'El nombre del viento', author: '/authors/32' });

    expect(created).toEqual({ id: '/books/9', title: 'A' });
    expect(calls[0]?.variables).toEqual({ input: { title: 'El nombre del viento', author: '/authors/32' } });
  });

  it('update merges the id into the input payload', async () => {
    const { transport, calls } = makeTransport((doc) =>
      doc.includes('updateBook') ? { updateBook: { book: { id: '/books/1', title: 'B' } } } : {},
    );
    const repo = createRepository(registry, transport, 'Book');

    const updated = await repo.update('/books/1', { title: 'B' });

    expect(updated.title).toBe('B');
    expect(calls[0]?.variables).toEqual({ input: { id: '/books/1', title: 'B' } });
  });

  it('remove sends { input: { id } } and returns true', async () => {
    const { transport, calls } = makeTransport(() => ({ deleteBook: { book: null } }));
    const repo = createRepository(registry, transport, 'Book');

    const ok = await repo.remove('/books/1');

    expect(ok).toBe(true);
    expect(calls[0]?.variables).toEqual({ input: { id: '/books/1' } });
  });

  it('call() exposes custom operations and forwards selection to the builder', async () => {
    const { transport, calls } = makeTransport((doc) => (doc.includes('featuredBook') ? { featuredBook: { id: '/books/1', title: 'F' } } : {}));
    const repo = createRepository(registry, transport, 'Book');

    const result = await repo.call<{ id: string }>('featuredBook', { id: '/books/1' });

    expect(result).toEqual({ id: '/books/1', title: 'F' });
    expect(calls[0]?.document).toContain('featuredBook');
    expect(calls[0]?.variables).toEqual({ id: '/books/1' });
  });

  it('call() throws when the operation is not present in the schema', async () => {
    const { transport } = makeTransport(() => ({}));
    const repo = createRepository(registry, transport, 'Book');

    await expect(repo.call('doesNotExist')).rejects.toThrow(/no encontrada/i);
  });
});

describe('Repository — page and flat collections', () => {
  it('normalizes page shape reading paginationInfo', async () => {
    const registry = new SchemaRegistry(buildSchema(pageSdl));
    const { transport } = makeTransport(() => ({
      authors: {
        collection: [{ id: '/authors/1', name: 'A' }],
        paginationInfo: { itemsPerPage: 15, lastPage: 1, totalCount: 1, hasNextPage: false },
      },
    }));
    const repo = createRepository(registry, transport, 'Author');

    const result = await repo.findAll({ filters: { page: 1 } });

    expect(result.items).toEqual([{ id: '/authors/1', name: 'A' }]);
    expect(result.totalCount).toBe(1);
  });

  it('normalizes flat shape directly', async () => {
    const registry = new SchemaRegistry(buildSchema(flatSdl));
    const { transport } = makeTransport(() => ({ statuss: [{ id: '/statuses/1', name: 'ACTIVO' }] }));
    const repo = createRepository(registry, transport, 'Status');

    const result = await repo.findAll();

    expect(result.items).toEqual([{ id: '/statuses/1', name: 'ACTIVO' }]);
    expect(result.totalCount).toBeUndefined();
  });
});

describe('GraphQLApiError violations', () => {
  it('flattens extensions.violations across multiple errors', () => {
    const err = new GraphQLApiError('Falló la mutación', [
      {
        message: 'a',
        extensions: { violations: [{ propertyPath: 'title', message: 'No puede estar vacío' }] },
      },
      {
        message: 'b',
        extensions: {
          violations: [
            { propertyPath: 'author', message: 'Debe existir' },
            { propertyPath: 'author', message: 'Es inválido' },
          ],
        },
      },
    ]);

    expect(err.violations).toHaveLength(3);
    expect(err.violations.map((v) => v.propertyPath)).toEqual(['title', 'author', 'author']);
  });
});
