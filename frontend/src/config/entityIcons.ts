export const entityIcons: Record<string, string> = {
  // IAM
  User: 'person',
  Role: 'badge',
  Permiso: 'policy',
  Action: 'lock',
  // Core business
  Boleto: 'airplane_ticket',
  Ruta: 'route',
  Bus: 'directions_bus',
  Estacion: 'location_on',
  Viaje: 'departure_board',
  Encomienda: 'inventory_2',
  Cliente: 'people',
  Localidad: 'map',
  // Supporting
  Option: 'settings',
  Terminal: 'location_city',
  Configuracion: 'tune',
  Tarifa: 'sell',
  Horario: 'schedule',
  Conductor: 'badge',
  Mantenimiento: 'build',
  Notificacion: 'notifications',
  Pago: 'payments',
  Reporte: 'assessment',
}

export function getEntityIcon(name: string): string {
  return entityIcons[name] || 'database'
}
