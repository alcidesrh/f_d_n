export async function createAction(data: any): Promise<any> {
  const api = useApi()
  return api.value.post('/actions', data)
}
