export async function updateAction(id: number, data: any): Promise<any> {
  const api = useApi()
  return api.value.put(`/actions/${id}`, data)
}
