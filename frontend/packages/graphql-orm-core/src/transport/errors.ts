export class GraphQLOrmError extends Error {}

export interface GraphQLViolation {
  propertyPath: string;
  message: string;
  code?: string;
}

export class GraphQLApiError extends GraphQLOrmError {
  constructor(
    message: string,
    public readonly graphQLErrors: ReadonlyArray<{ message: string; extensions?: Record<string, any> }>,
  ) {
    super(message);
  }

  /** Aplana extensions.violations de todos los errores (sección 3.8 / 12) */
  get violations(): GraphQLViolation[] {
    return this.graphQLErrors.flatMap((e) => (e.extensions?.violations as GraphQLViolation[]) ?? []);
  }
}

export class ClientValidationError extends GraphQLOrmError {
  constructor(
    message: string,
    public readonly issues: unknown,
  ) {
    super(message);
  }
}
