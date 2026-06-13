export async function deleteAction(id: number): Promise<any> {
  const api = useApi()
  return api.value.delete(`/actions/${id}`)
}
