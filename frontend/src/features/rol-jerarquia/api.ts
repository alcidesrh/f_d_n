/**
 * Transporte de la jerarquía de roles: los roles por GraphQL (CRUD genérico,
 * ids IRI) y el árbol por REST (`JerarquiaRolesController`, ids numéricos).
 */
import { gql } from '@apollo/client'
import { graphql } from '@/core/graphql/client'
import { http, request } from '@/core/http'
import type { TreeNode } from '@/core/outline/outline'

export interface RolDto {
  id: number
  nombre: string
}

const ROLES = gql`
  query RolJerarquiaRoles {
    roles {
      id
      nombre
    }
  }
`

export async function fetchRoles(): Promise<RolDto[]> {
  const result = await graphql.client.query<{ roles: Array<{ id: string; nombre: string }> }>({
    query: ROLES,
    fetchPolicy: 'network-only',
  })
  return (result.data?.roles ?? [])
    .map((rol) => ({ id: Number(rol.id.split('/').pop()), nombre: rol.nombre }))
    .sort((a, b) => a.nombre.localeCompare(b.nombre))
}

export const fetchJerarquia = () => http.get<TreeNode[]>('/rol-jerarquia')

export const saveJerarquia = (tree: TreeNode[]) =>
  request<TreeNode[]>('/rol-jerarquia', { method: 'PUT', body: tree })
