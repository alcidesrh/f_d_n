import type { GraphQLSchema } from 'graphql';
import { buildEntityDescriptor } from './entity-descriptor';
import type { EntityDescriptor } from '../../../types';

export class SchemaRegistry {
  private cache = new Map<string, EntityDescriptor>();

  constructor(public readonly schema: GraphQLSchema) {}

  describe(typeName: string): EntityDescriptor {
    let d = this.cache.get(typeName);
    if (!d) {
      d = buildEntityDescriptor(this.schema, typeName);
      this.cache.set(typeName, d);
    }
    return d;
  }

  warmUp(typeNames: string[]): void {
    typeNames.forEach((t) => this.describe(t));
    console.log(this.cache)
  }
}
