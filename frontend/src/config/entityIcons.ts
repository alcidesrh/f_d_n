export const entityIcons: Record<string, string> = {
	// IAM
	User: 'person',
	Role: 'badge',
	Permiso: 'policy',
	Action: 'lock',
	// Core business
	Enclave: 'location_on',
	Parada: 'back_hand',
	Estacion: 'subway',
	Trayecto: 'automation',
	BusMarca: 'sell',
	Nacion: 'public',
	Piloto: 'id_card',
	Asiento: 'airline_seat_recline_extra',
	Empresa: 'home_work',
	Boleto: 'transit_ticket',
	Ruta: 'route',
	Bus: 'directions_bus',
	Estacion: 'location_on',
	Viaje: 'departure_board',
	Encomienda: 'inventory_2',
	Cliente: 'people',
	Localidad: 'location_city',
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
	Status: 'badge',
	Usuario: 'person',
	ApiToken: 'key',
	User: 'person',
}

export function getEntityIcon(name: string): string {
	return entityIcons[name] || 'database'
}
