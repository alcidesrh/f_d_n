export async function listActions(params?: any): Promise<any> {
  const api = useApi()
  return api.value.get('/actions', { params })
}
