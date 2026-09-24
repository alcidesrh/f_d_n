/**
 * `IconGateway` sobre la API GraphQL.
 * Separado de `iconRelation.ts` para que este siga siendo importable sin
 * arrancar la app (tests del serializer).
 */
import { getEntity } from "@/core/entities/registry";
import { graphql } from "@/core/graphql/client";
import { useSchemaRepositoryStore } from "@/core/entities/schema";
import { ICON_ENTITY, type IconGateway } from "./iconRelation";

/** Gateway sobre la API GraphQL. */
export function apiIconGateway(): IconGateway {
  const metadata = () => useSchemaRepositoryStore().getEntityMetadata(ICON_ENTITY);
  return {
    async iconName(iri) {
      const item = await graphql.item<{ icon?: string | null } | null>(metadata(), iri);
      return item?.icon ?? null;
    },
    async findIri(name) {
      // El filtro `icon` del backend es parcial (`bus` también trae `bus-stop`):
      // se pide una página amplia y se compara exacto.
      const { items } = await graphql.collection<{ id: string; icon: string }>(metadata(), {
        filters: { icon: name },
        fields: ["id", "icon"],
        itemsPerPage: 200,
      });
      return items.find((item) => item.icon === name)?.id ?? null;
    },
    async create(name) {
      // `label` es obligatorio en todos los create*Input (propiedad pública de
      // `Base`), aunque el backend lo calcula desde `name`: se manda el mismo valor.
      const created = await getEntity<{ id: string }>(ICON_ENTITY).create({ icon: name, name, label: name });
      return created.id;
    },
  };
}
