<template>
  <section class="bg-gradient-to-br from-blue-900 via-blue-800 to-sky-700 text-white">
    <div class="mx-auto max-w-6xl px-4 pb-24 pt-10 md:pt-16">
      <h1 class="m-0 text-3xl font-bold leading-tight md:text-5xl">Viaje por Guatemala<br class="hidden md:block" /> con su asiento asegurado</h1>
      <p class="mt-3 max-w-2xl text-base text-blue-100 md:text-lg">
        Elija su salida, escoja el asiento en el croquis del bus y pague con tarjeta. Su boleto llega al correo.
      </p>
    </div>
  </section>
  <section class="mx-auto -mt-16 max-w-6xl px-4">
    <div class="panel shadow-lg">
      <BuscadorViaje @buscar="irASalidas" />
    </div>
  </section>
  <section class="mx-auto grid max-w-6xl gap-4 px-4 py-10 md:grid-cols-3">
    <div v-for="b in beneficios" :key="b.titulo" class="panel flex gap-3">
      <icon :name="b.icono" size="1.75rem" class="shrink-0 text-primary" />
      <div>
        <h2 class="m-0 text-base font-semibold">{{ b.titulo }}</h2>
        <p class="m-0 mt-1 text-sm text-muted-color">{{ b.texto }}</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import BuscadorViaje from '@/componentes/BuscadorViaje.vue'
import { diaISO } from '@/modelo'

const router = useRouter()
const beneficios = [
  { icono: 'armchair', titulo: 'Usted elige el asiento', texto: 'Vea en vivo los asientos libres y ocupados de cada bus.' },
  { icono: 'lock', titulo: 'Pago seguro', texto: 'Visa y Mastercard con verificación 3-D Secure de su banco.' },
  { icono: 'download', titulo: 'Boleto al instante', texto: 'Descárguelo al pagar y reciba una copia con su factura por correo.' },
]

function irASalidas(b: { origen: number; destino: number; fecha: Date }) {
  void router.push({ name: 'salidas', query: { origen: b.origen, destino: b.destino, fecha: diaISO(b.fecha) } })
}
</script>
