<!-- pages/TicketSalesPage.vue -->
<template>
	<q-page class="bg-neutral-100 dark:bg-neutral-950">
		<div class="mx-auto max-w-[1800px] p-2 md:p-4">
			<!-- Header -->
			<div
				class="mb-4 flex flex-col gap-3 rounded-4 bg-white p-4 shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-800 md:flex-row md:items-center md:justify-between"
			>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">Emitir Boletos</h1>

					<p class="mt-1 text-sm text-neutral-500">Venta rápida de boletos y selección visual de asientos</p>
				</div>

				<div class="flex flex-wrap gap-2">
					<q-btn color="primary" icon="sym_o_add" label="Nuevo Cliente" unelevated />

					<q-btn color="secondary" icon="sym_o_history" label="Historial" flat />
				</div>
			</div>

			<!-- Main Layout -->
			<div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_420px]">
				<!-- LEFT -->
				<div class="flex flex-col gap-4">
					<!-- Filters -->
					<q-card flat class="rounded-4 shadow-sm">
						<q-card-section class="space-y-4">
							<div class="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_auto_auto_auto]">
								<!-- Cliente -->
								<FormKit type="select" label="Cliente" placeholder="Seleccione cliente" :options="clientes" outer-class="w-full" input-class="fk-input" />

								<q-btn icon="sym_o_add" color="primary" class="h-[42px] self-end" unelevated />

								<q-btn icon="sym_o_edit" color="secondary" class="h-[42px] self-end" flat />

								<q-btn icon="sym_o_search" color="dark" class="h-[42px] self-end" flat />
							</div>

							<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
								<FormKit type="datetime" label="Fecha de salida" value="2026-05-25" />

								<FormKit type="select" label="Estación origen" :options="estaciones" value="gua" input-class="fk-input" />
							</div>
						</q-card-section>
					</q-card>

					<!-- Trips -->
					<q-card flat class="rounded-4 shadow-sm">
						<q-card-section class="pb-0">
							<div class="mb-3 flex items-center justify-between">
								<div>
									<h2 class="text-lg font-semibold">Salidas disponibles</h2>

									<p class="text-sm text-neutral-500">Seleccione una salida para ver los asientos</p>
								</div>

								<q-input dense outlined placeholder="Buscar..." class="w-[220px]">
									<template #prepend>
										<q-icon name="sym_o_search" />
									</template>
								</q-input>
							</div>
						</q-card-section>

						<q-separator />

						<!-- Desktop table -->
						<div class="hidden xl:block">
							<q-table flat :rows="trips" :columns="columns" row-key="id" :pagination="{ rowsPerPage: 8 }" selection="single" v-model:selected="selectedTrip" class="ticket-table">
								<template #body-cell-status="props">
									<q-td :props="props">
										<q-badge :color="props.value === 'Abordando' ? 'orange' : 'positive'" rounded>
											{{ props.value }}
										</q-badge>
									</q-td>
								</template>
							</q-table>
						</div>

						<!-- Mobile cards -->
						<div class="grid gap-3 p-3 xl:hidden">
							<q-card
								v-for="trip in trips"
								:key="trip.id"
								flat
								bordered
								class="rounded-3 border-neutral-200 transition-all hover:shadow-md"
								:class="selectedTrip[0]?.id === trip.id ? 'ring-2 ring-primary' : ''"
								@click="selectedTrip = [trip]"
							>
								<q-card-section>
									<div class="flex items-start justify-between gap-4">
										<div>
											<div class="text-lg font-semibold">
												{{ trip.time }}
											</div>

											<div class="mt-1 text-sm text-neutral-500">{{ trip.origin }} → {{ trip.destination }}</div>
										</div>

										<q-badge :color="trip.status === 'Abordando' ? 'orange' : 'positive'">
											{{ trip.status }}
										</q-badge>
									</div>

									<div class="mt-4 grid grid-cols-2 gap-y-2 text-sm text-neutral-600">
										<div>
											<span class="font-medium">Empresa:</span>
											{{ trip.company }}
										</div>

										<div>
											<span class="font-medium">Bus:</span>
											{{ trip.bus }}
										</div>

										<div class="col-span-2">
											<span class="font-medium">Itinerario:</span>
											{{ trip.route }}
										</div>
									</div>
								</q-card-section>
							</q-card>
						</div>
					</q-card>

					<!-- Intermediate stations -->
					<q-card flat class="rounded-4 shadow-sm">
						<q-card-section>
							<div class="mb-4">
								<h2 class="text-lg font-semibold">Estaciones intermedias</h2>

								<p class="text-sm text-neutral-500">Ajuste el abordaje o descenso del pasajero</p>
							</div>

							<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
								<FormKit type="select" label="Sube en" :options="boardingStations" value="gua" input-class="fk-input" />

								<FormKit type="select" label="Baja en" :options="dropStations" value="flores" input-class="fk-input" />
							</div>

							<div class="mt-4">
								<FormKit
									type="textarea"
									label="Observación"
									placeholder="Especifique si el cliente desea bajarse en algún lugar intermedio..."
									input-class="fk-input min-h-[100px]"
								/>
							</div>

							<q-checkbox v-model="originPickup" label="Utilizar dirección de estación origen" class="mt-4" />

							<div class="mt-4">
								<FormKit type="text" label="Dirección" value="17 CALLE 8-46, ZONA 1" input-class="fk-input" />
							</div>
						</q-card-section>
					</q-card>

					<!-- Purchase summary -->
					<q-card flat class="rounded-4 shadow-sm">
						<q-card-section>
							<div class="mb-4 flex items-center justify-between">
								<div>
									<h2 class="text-lg font-semibold">Resumen de compra</h2>

									<p class="text-sm text-neutral-500">Asientos seleccionados y total</p>
								</div>

								<div class="rounded-2 bg-primary/10 px-4 py-2">
									<div class="text-xs uppercase tracking-wide text-primary">Total</div>

									<div class="text-xl font-bold text-primary">Q 320.00</div>
								</div>
							</div>

							<q-table flat bordered :rows="summaryRows" :columns="summaryColumns" row-key="seat" hide-pagination />

							<div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
								<q-btn color="secondary" icon="sym_o_redeem" label="Cortesía" flat />

								<q-btn color="primary" icon="receipt_long" label="Facturar" unelevated />

								<q-btn color="primary" outline icon="compare_arrows" label="Facturar otra estación" />
							</div>
						</q-card-section>
					</q-card>
				</div>

				<!-- RIGHT SIDEBAR -->
				<aside class="xl:sticky xl:top-4 xl:h-fit">
					<q-card flat class="rounded-4 shadow-sm">
						<q-card-section>
							<div class="mb-4 flex items-center justify-between">
								<div>
									<h2 class="text-lg font-semibold">Asientos</h2>

									<p class="text-sm text-neutral-500">Bus TPB050C</p>
								</div>

								<q-badge color="primary" rounded> {{ selectedSeats.length }} seleccionados </q-badge>
							</div>

							<!-- Driver -->
							<div class="mb-6 flex h-20 items-center justify-center rounded-3 bg-neutral-100 text-sm font-medium text-neutral-500 dark:bg-neutral-800">Conductor</div>

							<!-- Seat map -->
							<div class="grid grid-cols-4 gap-3">
								<button
									v-for="seat in seats"
									:key="seat.number"
									class="seat"
									:class="[seat.status, selectedSeats.includes(seat.number) ? 'seat-selected' : '']"
									@click="toggleSeat(seat)"
								>
									<div class="text-xs font-semibold">
										{{ seat.number }}
									</div>

									<q-icon name="sym_o_event_seat" size="24px" />
								</button>
							</div>

							<!-- Legend -->
							<div class="mt-6 grid grid-cols-2 gap-3 text-sm">
								<div class="flex items-center gap-2">
									<div class="h-4 w-4 rounded bg-lime-200 ring-1 ring-lime-400" />
									Disponible
								</div>

								<div class="flex items-center gap-2">
									<div class="h-4 w-4 rounded bg-red-200 ring-1 ring-red-400" />
									Ocupado
								</div>

								<div class="flex items-center gap-2">
									<div class="h-4 w-4 rounded bg-primary" />
									Seleccionado
								</div>

								<div class="flex items-center gap-2">
									<div class="h-4 w-4 rounded bg-yellow-200 ring-1 ring-yellow-400" />
									Reservado
								</div>
							</div>
						</q-card-section>
					</q-card>
				</aside>
			</div>
		</div>
	</q-page>
</template>

<script setup>
	import { ref } from 'vue'

	const originPickup = ref(true)

	const selectedTrip = ref([])

	const selectedSeats = ref([12, 19])

	const clientes = [
		{ label: 'Consumidor Final', value: 'cf' },
		{ label: 'Juan Pérez', value: 'jp' },
	]

	const estaciones = [
		{ label: 'GUA - Guatemala', value: 'gua' },
		{ label: 'Flores, Petén', value: 'flores' },
	]

	const boardingStations = estaciones
	const dropStations = estaciones

	const columns = [
		{
			name: 'time',
			label: 'Fecha y Hora',
			field: 'time',
			align: 'left',
		},
		{
			name: 'origin',
			label: 'Origen',
			field: 'origin',
		},
		{
			name: 'destination',
			label: 'Destino',
			field: 'destination',
		},
		{
			name: 'company',
			label: 'Empresa',
			field: 'company',
		},
		{
			name: 'route',
			label: 'Itinerario',
			field: 'route',
		},
		{
			name: 'bus',
			label: 'Bus',
			field: 'bus',
		},
		{
			name: 'status',
			label: 'Estado',
			field: 'status',
		},
	]

	const trips = [
		{
			id: 1,
			time: '05:45 AM',
			origin: 'Guatemala',
			destination: 'Santa Elena',
			company: 'Maya de Oro',
			route: 'Económica',
			bus: '08',
			status: 'Abordando',
		},
		{
			id: 2,
			time: '06:30 AM',
			origin: 'Guatemala',
			destination: 'Quetzaltenango',
			company: 'Pionera',
			route: 'Clase Oro',
			bus: '61',
			status: 'Programada',
		},
		{
			id: 3,
			time: '08:00 AM',
			origin: 'Guatemala',
			destination: 'Flores',
			company: 'Maya de Oro',
			route: 'VIP',
			bus: '22',
			status: 'Programada',
		},
	]

	const seats = Array.from({ length: 40 }).map((_, i) => ({
		number: i + 1,
		status: [1, 2, 7, 8].includes(i + 1) ? 'occupied' : [4, 14].includes(i + 1) ? 'reserved' : 'available',
	}))

	const summaryColumns = [
		{
			name: 'seat',
			label: 'Asiento',
			field: 'seat',
		},
		{
			name: 'passenger',
			label: 'Pasajero',
			field: 'passenger',
		},
		{
			name: 'price',
			label: 'Precio',
			field: 'price',
		},
	]

	const summaryRows = [
		{
			seat: '12',
			passenger: 'Juan Pérez',
			price: 'Q 160.00',
		},
		{
			seat: '19',
			passenger: 'Juan Pérez',
			price: 'Q 160.00',
		},
	]

	function toggleSeat(seat) {
		if (seat.status !== 'available') return

		if (selectedSeats.value.includes(seat.number)) {
			selectedSeats.value = selectedSeats.value.filter((s) => s !== seat.number)
		} else {
			selectedSeats.value.push(seat.number)
		}
	}
</script>

<style scoped>
	.seat {
		@apply flex h-16 flex-col items-center justify-center rounded-2 border text-neutral-700 transition-all;
	}

	.available {
		@apply border-lime-300 bg-lime-50 hover:scale-105 hover:bg-lime-100;
	}

	.occupied {
		@apply cursor-not-allowed border-red-300 bg-red-100 text-red-400 opacity-70;
	}

	.reserved {
		@apply border-yellow-300 bg-yellow-100;
	}

	.seat-selected {
		@apply border-primary bg-primary text-white shadow-lg scale-105;
	}

	:deep(.q-table th) {
		@apply bg-neutral-100 text-sm font-semibold dark:bg-neutral-800;
	}

	:deep(.fk-input) {
		@apply w-full rounded-2 border border-neutral-300 bg-white px-4 py-2 outline-none transition-all focus:border-primary dark:border-neutral-700 dark:bg-neutral-900;
	}
</style>
s
