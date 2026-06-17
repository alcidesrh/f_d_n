<template>
	<q-page class="q-pa-md bg-grey-1">
		<div class="row q-col-gutter-md">
			<!-- Left Panel: Form and Tables -->
			<div class="col-12 col-md-8">
				<q-card class="q-pa-md q-mb-md shadow-1">
					<FormKit type="form" :actions="false">
						<!-- Cliente Search -->
						<div class="row items-center q-col-gutter-sm q-mb-md">
							<div class="col-12 col-md-9 text-subtitle2 text-grey-8">
								CLIENTE: NIT (sin guión) / NOMBRE / DOCUMENTO
							</div>
							<div class="col-12 col-md-9">
								<FormKit
									type="text_search"
									name="cliente_search"
									placeholder="CF / CLIENTE."
									:context="{ id: 'cliente_search', inputType: 'text' }"
								/>
							</div>
							<div class="col-12 col-md-3 flex q-gutter-x-sm">
								<q-btn icon="sym_o_add" dense unelevated color="grey-3" text-color="black" />
								<q-btn icon="sym_o_edit" dense unelevated color="grey-3" text-color="black" />
								<q-btn icon="sym_o_search" dense unelevated color="grey-3" text-color="black" />
							</div>
						</div>

						<!-- Departure Details -->
						<div class="row q-col-gutter-md q-mb-md">
							<div class="col-12 col-md-4">
								<div class="text-caption text-grey-8 q-mb-xs">Fecha de Salida</div>
								<FormKit
									type="datetime"
									name="fecha_salida"
									:context="{ id: 'fecha_salida' }"
								/>
							</div>
							<div class="col-12 col-md-8">
								<div class="text-caption text-grey-8 q-mb-xs">Estación Origen de Salida</div>
								<FormKit
									type="select"
									name="estacion_origen"
									:options="[{ label: 'GUA-Guatemala', value: 'gua' }]"
									:context="{ id: 'estacion_origen' }"
								/>
							</div>
						</div>

						<!-- Departures Table -->
						<div class="text-subtitle2 text-grey-8 q-mb-xs">Salidas</div>
						<q-table
							:rows="salidas"
							:columns="columnasSalidas"
							row-key="id"
							dense
							flat
							bordered
							class="q-mb-md"
							:rows-per-page-options="[10, 20, 50]"
						>
							<template v-slot:body="props">
								<q-tr :props="props" :class="props.row.id === 10 ? 'bg-blue-1' : ''">
									<q-td v-for="col in props.cols" :key="col.name" :props="props">
										{{ col.value }}
									</q-td>
								</q-tr>
							</template>
						</q-table>

						<!-- Route Details -->
						<div class="row q-col-gutter-md q-mb-sm">
							<div class="col-12 col-md-6">
								<div class="text-caption text-grey-8 q-mb-xs">Sube En</div>
								<FormKit
									type="select"
									name="sube_en"
									:options="[{ label: '09:00PM GUA-Guatemala', value: 'gua' }]"
									:context="{ id: 'sube_en' }"
								/>
							</div>
							<div class="col-12 col-md-6">
								<div class="text-caption text-grey-8 q-mb-xs">Baja En</div>
								<FormKit
									type="select"
									name="baja_en"
									:options="[{ label: 'SEP-Santa Elena, Flores, Peten', value: 'sep' }]"
									:context="{ id: 'baja_en' }"
								/>
							</div>
						</div>

						<div class="q-mb-sm">
							<div class="text-caption text-grey-8 q-mb-xs">Observación</div>
							<FormKit
								type="text"
								name="observacion"
								placeholder="Especifique si el cliente desea bajarse en algún lugar intermedio."
								:context="{ id: 'observacion' }"
							/>
						</div>

						<div class="q-mb-sm">
							<div class="text-caption text-grey-8 q-mb-xs">Estación dirección</div>
							<FormKit
								type="text"
								name="estacion_direccion"
								disabled
								value="17 CALLE 8-46, ZONA 1"
								class="bg-grey-2"
								:context="{ id: 'estacion_direccion' }"
							/>
						</div>

						<div class="q-mb-md">
							<FormKit
								type="checkbox"
								name="usar_origen"
								label="Utilizar desde la estación origen de la salida"
								:context="{ id: 'usar_origen', label: 'Utilizar desde la estación origen de la salida', _value: true }"
							/>
						</div>

						<!-- Assigned Seats Table -->
						<q-table
							:rows="asientosAsignados"
							:columns="columnasAsientos"
							row-key="nro"
							dense
							flat
							bordered
							hide-pagination
						>
							<template v-slot:no-data>
								<div class="full-width row flex-center text-grey-6 q-pa-sm">
									No existen elementos.
								</div>
							</template>
						</q-table>
					</FormKit>
				</q-card>
			</div>

			<!-- Right Panel: Seat Map -->
			<div class="col-12 col-md-4">
				<q-card class="shadow-1 h-full">
					<q-tabs
						v-model="tab"
						dense
						class="text-grey"
						active-color="primary"
						indicator-color="primary"
						align="left"
						narrow-indicator
					>
						<q-tab name="nivel1" label="Nivel 1" />
						<q-tab name="nivel2" label="Nivel 2" />
					</q-tabs>

					<q-separator />

					<q-tab-panels v-model="tab" animated>
						<q-tab-panel name="nivel1">
							<div class="row justify-between q-pa-sm q-col-gutter-x-md">
								<!-- Left Column (Seats 1-2 per row) -->
								<div class="col-6 grid gap-y-2">
									<div class="row justify-between" v-for="row in [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]" :key="'l'+row">
										<SeatIcon :nro="(row-1)*4 + 1" estado="ocupado" />
										<SeatIcon :nro="(row-1)*4 + 2" :estado="row === 3 ? 'libre' : (row === 4 ? 'libre' : 'ocupado')" />
									</div>
								</div>
								
								<!-- Right Column (Seats 3-4 per row) -->
								<div class="col-6 grid gap-y-2">
									<div class="row justify-between" v-for="row in [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]" :key="'r'+row">
										<!-- Aisle space -->
										<div v-if="row === 3" class="flex flex-center" style="width: 40px; height: 40px;">
											<q-icon name="sym_o_exit_to_app" color="green" size="md" />
										</div>
										<SeatIcon v-else :nro="(row-1)*4 + 3" estado="ocupado" />
										
										<SeatIcon :nro="(row-1)*4 + 4" estado="ocupado" />
									</div>
								</div>
							</div>
							<div class="q-mt-md text-caption text-grey-8">
								Cantidad: 42/54
							</div>
						</q-tab-panel>

						<q-tab-panel name="nivel2">
							<div class="text-center text-grey q-pa-lg">
								Mapa Nivel 2
							</div>
						</q-tab-panel>
					</q-tab-panels>
				</q-card>
			</div>
		</div>

		<!-- Action Bar -->
		<q-footer class="bg-white text-black bordered q-pa-sm shadow-up-2">
			<div class="row justify-end q-gutter-sm">
				<q-btn label="Cortesia" color="primary" unelevated />
				<q-btn label="Facturar" color="primary" unelevated />
				<q-btn label="Facturar Otra Estación" color="primary" unelevated />
				<q-btn label="Cancelar" outline color="primary" />
			</div>
		</q-footer>
	</q-page>
</template>

<script setup>
import { ref } from 'vue'

const tab = ref('nivel1')

const columnasSalidas = [
	{ name: 'fecha_hora', label: 'Fecha y Hora', field: 'fecha_hora', align: 'left', sortable: true },
	{ name: 'origen', label: 'Origen', field: 'origen', align: 'left', sortable: true },
	{ name: 'destino', label: 'Destino', field: 'destino', align: 'left', sortable: true },
	{ name: 'empresa', label: 'Empresa', field: 'empresa', align: 'left', sortable: true },
	{ name: 'itinerario', label: 'Itinerario', field: 'itinerario', align: 'left', sortable: true },
	{ name: 'bus', label: 'Bus', field: 'bus', align: 'left', sortable: true },
	{ name: 'estado', label: 'Estado', field: 'estado', align: 'left', sortable: true }
]

const salidas = [
	{ id: 1, fecha_hora: '15-06-2026 05:45 AM', origen: 'GUA-Guatemala', destino: 'SEP-Santa Elena, Flores', empresa: 'PIONERA', itinerario: 'TPB040H - Clase Oro / Esp', bus: '48 - TPB040F - Clase Oro', estado: 'Iniciada' },
	{ id: 2, fecha_hora: '15-06-2026 06:30 AM', origen: 'GUA-Guatemala', destino: 'QUE-Quetzaltenango', empresa: 'PIONERA', itinerario: 'TPB048A - Clase Oro / May', bus: '62 - TPB048F - Clase Oro', estado: 'Iniciada' },
	{ id: 3, fecha_hora: '15-06-2026 10:00 AM', origen: 'GUA-Guatemala', destino: 'SEP-Santa Elena, Flores', empresa: 'PIONERA', itinerario: 'TPB046H - Clase Oro / Esp', bus: '61 - TPB048F - Clase Oro', estado: 'Iniciada' },
	{ id: 4, fecha_hora: '15-06-2026 11:55 AM', origen: 'GUA-Guatemala', destino: 'EST-El Estor', empresa: 'MAYA DE ORO', itinerario: 'TPB046D - Starbus', bus: '40019 - TPB046D - Starbus', estado: 'Iniciada' },
	{ id: 5, fecha_hora: '15-06-2026 01:00 PM', origen: 'GUA-Guatemala', destino: 'SEP-Santa Elena, Flores', empresa: 'PIONERA', itinerario: 'TPB046H - Clase Oro / Esp', bus: '95 - TPB060D - Clase de O', estado: 'Iniciada' },
	{ id: 6, fecha_hora: '15-06-2026 03:30 PM', origen: 'GUA-Guatemala', destino: 'ENJ-El Naranjo', empresa: 'PIONERA', itinerario: 'TPB046H - Clase Oro / Esp', bus: '60 - TPB050F - Clase Oro', estado: 'Iniciada' },
	{ id: 7, fecha_hora: '15-06-2026 03:30 PM', origen: 'GUA-Guatemala', destino: 'QUE-Quetzaltenango', empresa: 'PIONERA', itinerario: 'TPB048A - Clase Oro / May', bus: '71 - TPB040H - Clase Oro', estado: 'Iniciada' },
	{ id: 8, fecha_hora: '15-06-2026 05:55 PM', origen: 'GUA-Guatemala', destino: 'EST-El Estor', empresa: 'MAYA DE ORO', itinerario: 'TPB046D - Starbus', bus: '40019 - TPB046D - Starbus', estado: 'Iniciada' },
	{ id: 9, fecha_hora: '15-06-2026 06:00 PM', origen: 'GUA-Guatemala', destino: 'MDM-Melchor de Mencos', empresa: 'PIONERA', itinerario: 'TPB048C - Clase Oro Espe', bus: '64 - TPB051C - Clase Oro', estado: 'Iniciada' },
	{ id: 10, fecha_hora: '15-06-2026 09:00 PM', origen: 'GUA-Guatemala', destino: 'SEP-Santa Elena, Flores', empresa: 'PIONERA', itinerario: 'TPB054E - Clase Oro GLS', bus: '98 - TPB054E - Clase Oro', estado: 'Iniciada' }
]

const columnasAsientos = [
	{ name: 'nro', label: 'Nro. Asiento', field: 'nro', align: 'left' },
	{ name: 'cliente', label: 'Cliente Boleto', field: 'cliente', align: 'left' }
]

const asientosAsignados = []

import SeatIcon from './SeatIcon.vue'
</script>

<style scoped>
/* Scoped styles can go here if we need specific adjustments, UnoCSS handles most */
.gap-y-2 {
	row-gap: 0.5rem;
}
</style>
