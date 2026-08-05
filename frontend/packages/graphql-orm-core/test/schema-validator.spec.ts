import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import { SchemaRegistry } from '../src/schema/schema-registry';
import { buildEntityDescriptor } from '../src/schema/entity-descriptor';
import { createInputValidator } from '../src/validation/schema-validator';

describe('SchemaValidator', () => {
  it('validates required fields successfully', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      input createBookInput { title: String!, author: String, clientMutationId: String }
      type createBookPayload { book: Book }
      type Query { book(id: ID!): Book }
      type Mutation { createBook(input: createBookInput!): createBookPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, buildEntityDescriptor(registry.schema, 'Book').mutations.create!.inputFields ?? []);

    expect(validate({ title: 'Don Quijote', author: '/authors/1' }).success).toBe(true);
  });

  it('fails when non-null field is missing', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      input createBookInput { title: String!, author: String }
      type createBookPayload { book: Book }
      type Query { book(id: ID!): Book }
      type Mutation { createBook(input: createBookInput!): createBookPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, buildEntityDescriptor(registry.schema, 'Book').mutations.create!.inputFields ?? []);

    expect(validate({ author: '/authors/1' }).success).toBe(false);
  });

  it('validates IRI format on object-kind relation descriptors', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      type Author { id: ID!, name: String! }
      input createBookInput { title: String!, author: ID }
      type createBookPayload { book: Book }
      type Query { book(id: ID!): Book }
      type Mutation { createBook(input: createBookInput!): createBookPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, [
      { name: 'title', typeName: 'String', kind: 'scalar', isList: false, isNonNull: true },
      { name: 'author', typeName: 'Author', kind: 'object', isList: false, isNonNull: false },
    ]);

    expect(validate({ title: 'Don Quijote', author: '/authors/1' }).success).toBe(true);
    const invalid = validate({ title: 'Don Quijote', author: 'not-an-iri' });
    expect(invalid.success).toBe(false);
    if (!invalid.success) {
      expect(JSON.stringify(invalid.error.issues)).toContain('IRI');
    }
  });

  it('validates enums against their schema values', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      enum Genre { FICTION NON_FICTION }
      input createBookInput { title: String!, genre: Genre }
      type createBookPayload { book: Book }
      type Query { book(id: ID!): Book }
      type Mutation { createBook(input: createBookInput!): createBookPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, buildEntityDescriptor(registry.schema, 'Book').mutations.create!.inputFields ?? []);

    expect(validate({ title: 'x', genre: 'FICTION' }).success).toBe(true);
    expect(validate({ title: 'x', genre: 'BOGUS' }).success).toBe(false);
  });

  it('recursively validates nested input-object relations', () => {
    const sdl = `
      type Book { id: ID!, title: String! }
      input AuthorInput { name: String! }
      input createBookInput { title: String!, author: AuthorInput }
      type createBookPayload { book: Book }
      type Query { book(id: ID!): Book }
      type Mutation { createBook(input: createBookInput!): createBookPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, buildEntityDescriptor(registry.schema, 'Book').mutations.create!.inputFields ?? []);

    expect(validate({ title: 'x', author: { name: 'Rothfuss' } }).success).toBe(true);
    expect(validate({ title: 'x', author: {} }).success).toBe(false); // name es NonNull dentro del anidado
  });

  it('does not infinitely recurse on cyclic input types', () => {
    const sdl = `
      type Category { id: ID!, name: String! }
      input CategoryInput { name: String!, parent: CategoryInput }
      input createCategoryInput { name: String!, parent: CategoryInput }
      type createCategoryPayload { category: Category }
      type Query { category(id: ID!): Category }
      type Mutation { createCategory(input: createCategoryInput!): createCategoryPayload }
    `;
    const registry = new SchemaRegistry(buildSchema(sdl));
    const { validate } = createInputValidator(registry, buildEntityDescriptor(registry.schema, 'Category').mutations.create!.inputFields ?? []);

    const result = validate({ name: 'raíz', parent: { name: 'hija' } });
    expect(result.success).toBe(true);
  });
});
