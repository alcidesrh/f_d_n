export async function showAction(id: number): Promise<any> {
  const api = useApi()
  return api.value.get(`/actions/${id}`)
}
