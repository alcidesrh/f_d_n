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
					<div class="btn-test">
						<button class="kimi">Kimi K3</button>
						<button class="chatgpt">Chatgpt</button>
						<button class="chatgpt second">Chatgpt 2</button>
						<button class="claude"><span>Claude</span></button>
						<button class="gemenis">Gemenis</button>
						<button class="grok">Grok</button>
						<button class="grok second">Grok Second</button>
					</div>
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

onMounted(() => {
	const buttons = document.querySelectorAll('button')

	buttons.forEach((button) => {
		button.addEventListener('click', () => {
			// 1. Remove the 'active' class from all other buttons first
			// buttons.forEach((btn) => btn.classList.remove('active'))

			// 2. Add the 'active' class to the currently clicked button
			button.classList.toggle('active')
		})
	})
})
</script>

<style scoped lang="scss">
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

/* CHATGPT------------------------------------------------------------ */
/* button {
	background: #4f8ef7;
	color: white;
	border: none;
	padding: 12px 24px;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.1s ease;

	box-shadow:
		0 5px 0 #2f5cb5,
		0 8px 12px rgba(0, 0, 0, 0.25);
}
button.active {
	transform: translateY(5px);

	box-shadow:
		0 0 0 #2f5cb5,
		0 2px 4px rgba(0, 0, 0, 0.2);
} */
/* button {
	background: #4f8ef7;
	color: white;
	border: none;
	border-radius: 8px;
	padding: 12px 24px;

	box-shadow:
		0 4px 0 #2f5cb5,
		0 6px 10px rgba(0, 0, 0, 0.25);
}

button.active {
	transform: translateY(4px);

	box-shadow:
		inset 0 3px 6px rgba(0, 0, 0, 0.35),
		0 1px 2px rgba(0, 0, 0, 0.15);
} */
.btn-test {
	& > .chatgpt {
		$bg: -alpha($surface-3, 1);
		$bg-light: -alpha($surface-1, 1);
		$bg-dark: -alpha($surface-6, 1);

		appearance: none;
		border: 0;
		border-radius: 12px;
		padding: 0.9rem 1.6rem;
		font: 600 15px system-ui;
		color: white;
		cursor: pointer;

		background: linear-gradient(to bottom, $bg-light, $bg);
		transform: translateY(0);
		transition:
			transform 120ms cubic-bezier(0.2, 0.8, 0.4, 1),
			box-shadow 120ms cubic-bezier(0.2, 0.8, 0.4, 1),
			background 120ms;
		&:hover {
			filter: brightness(1.05);
		}
		&:not(.second) {
			box-shadow:
				0 6px 0 $bg-dark,
				0 8px 18px rgba(0, 0, 0, 0.18);
			&.active {
				transform: translateY(5px);
				background: linear-gradient(to bottom, $bg, $bg-dark);
				box-shadow:
					0 1px 0 $bg-dark,
					0 2px 6px rgba(0, 0, 0, 0.12);
			}
		}
		&.second {
			box-shadow:
				inset 0 1px rgba(255, 255, 255, 0.25),
				0 6px 0 $bg-dark,
				0 8px 18px rgba(0, 0, 0, 0.18);

			&.active {
				box-shadow:
					inset 0 2px 4px rgba(0, 0, 0, 0.18),
					inset 0 1px rgba(255, 255, 255, 0.08),
					0 1px 0 #3f36d6,
					0 2px 5px rgba(0, 0, 0, 0.12);
			}
		}
	}
	/* GEMENIS------------------------------------------------------------ */
	& > .gemenis {
		padding: 12px 24px;
		font-size: 16px;
		font-weight: 600;
		font-family: sans-serif;
		color: #333;
		background-color: #f0f0f0;
		border: none;
		border-radius: 8px;
		cursor: pointer;
		outline: none;
		box-shadow:
			0 4px 6px rgba(0, 0, 0, 0.1),
			0 1px 3px rgba(0, 0, 0, 0.08);
		transition:
			transform 0.1s ease,
			box-shadow 0.1s ease;
		&:hover {
			background-color: #e8e8e8;
		}
		&.active {
			transform: translateY(3px);
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
		}
	}

	/* KIMI k3--------------------------------------------------- */
	& > .kimi {
		padding: 12px 24px;
		border: 1px solid $surface-3;
		box-shadow:
			0 4px 6px -1px rgba(0, 0, 0, 0.1),
			0 2px 4px -2px rgba(0, 0, 0, 0.06),
			0 8px 16px -4px rgba(0, 0, 0, 0.04);

		&.active {
			box-shadow:
				inset 0 2px 4px 0 rgba(0, 0, 0, 0.08),
				0 1px 1px 0 rgba(0, 0, 0, 0.04);
			transform: translateY(1px);
		}
	}

	// CLAUDE ---------------------------------

	& > .claude {
		padding: 12px 24px;
		border: 1px solid $surface-3;
		background: #e0e0e0;
		// border: none;
		// border-radius: 12px;
		box-shadow:
			-6px -6px 12px rgba(255, 255, 255, 0.55),
			2px 2px 6px -alpha($surface-6, 1);
		transition:
			box-shadow 1180ms ease,
			transform 180ms ease;

		&.active {
			border: 1px solid $surface-3 !important;
			& > * {
				opacity: 0.7;
			}
			background-color: $surface-2 !important;
			box-shadow:
				inset -4px -4px 8px rgba(255, 255, 255, 0.5),
				inset 0px 0px 9px 2px -alpha($surface-6, 1);
			transform: scale(0.98);
		}
	}
	& > .grok {
		&:not(.second) {
			padding: 16px 32px;
			font-size: 16px;
			font-weight: 600;
			color: #333;
			background: #f0f0f0;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			/* Efecto levantado (raised) */
			box-shadow:
				0 8px 0 #d0d0d0,
				/* sombra inferior principal */ 0 8px 15px rgba(0, 0, 0, 0.2); /* sombra suave */
			transition: all 0.15s ease; /* transición suave */
			outline: none;
			user-select: none;
			&.active {
				transform: translateY(4px); /* se mueve ligeramente hacia abajo */
				box-shadow:
					0 4px 0 #d0d0d0,
					/* sombra más corta */ 0 4px 8px rgba(0, 0, 0, 0.15),
					inset 0 2px 4px rgba(0, 0, 0, 0.1); /* sombra interna para efecto hundido */
			}

			/* Hover opcional (para mejor UX) */
			&:hover {
				background: #e8e8e8;
				box-shadow:
					0 10px 0 #d0d0d0,
					0 10px 18px rgba(0, 0, 0, 0.22);
			}
		}
		&.second {
			padding: 14px 28px;
			font-size: 16px;
			font-weight: 600;
			color: #222;
			background: #f5f5f5;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			box-shadow:
				0 6px 0 #c8c8c8,
				0 8px 16px rgba(0, 0, 0, 0.18);
			transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
			&.active {
				box-shadow:
					0 2px 0 #c8c8c8,
					0 3px 8px rgba(0, 0, 0, 0.15),
					inset 0 3px 6px rgba(0, 0, 0, 0.1);
				transform: translateY(4px);
			}
		}
	}
	& > button {
		border: 1px solid $surface-4 !important;
		background-color: white !important;
		color: $surface-6 !important;
		font-weight: 600 !important;
		padding: 12px 24px !important;
		margin: 15px !important;
		border-radius: 4px !important;
	}
}
</style>
