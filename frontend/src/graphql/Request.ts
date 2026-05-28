// src/graphql/entityQueryBuilder.js
import gql from "graphql-tag";

export function buildListQuery(entity, fields) {
  const selection = fields.map((f) => f.name).join("\n");

  return gql`
    query {
      ${entity.toLowerCase()}List {
        ${selection}
      }
    }
  `;
}
