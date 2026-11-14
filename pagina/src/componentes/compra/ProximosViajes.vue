<!--
  Próximos viajes comprados del visitante, bajo el buscador. Solo aparece si
  el navegador tiene la llave de «Mis compras» y hay viajes con fecha futura;
  si no, no muestra nada. Quien ya eligió una búsqueda no lo ve (lo oculta
  la página de inicio).
-->
<template>
  <section v-if="proximos.length" class="contenedor mt-6 flex flex-col gap-3 max-md:px-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="m-0 flex items-center gap-2 text-lg font-semibold">
        <icon name="calendar" class="text-marca-700" />{{ t('misCompras.futuros') }}
      </h2>
      <RouterLink :to="{ name: 'misCompras', params: { idioma: locale } }" class="text-sm font-medium no-underline">
        {{ t('misCompras.titulo') }} <icon name="arrow-right" size="1rem" />
      </RouterLink>
    </div>

    <article v-for="v in proximos" :key="v.id" class="panel flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <p class="m-0 text-base font-semibold">{{ v.origen }} → {{ v.destino }}</p>
        <p class="m-0 text-sm"><HoraSubida v-if="v.boletos[0]" :s="v.boletos[0].salida" /></p>
        <p class="m-0 mt-1 text-sm text-muted-color">
          {{ t('compra.asientos') }}: {{ v.boletos.map((b) => `${b.asiento}${b.clase === 'B' ? ' B' : ''}`).join(', ') }}
        </p>
      </div>
      <RouterLink
        v-if="v.editable"
        :to="{ name: 'modificarViaje', params: { idioma: locale, viaje: v.id } }"
        class="no-underline"
      >
        <Button class="w-full sm:w-auto"><icon name="edit" size="1.1rem" /><span>{{ t('misCompras.modificar') }}</span></Button>
      </RouterLink>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import HoraSubida from '@/componentes/HoraSubida.vue'
import { guardarLlave, leerLlave } from '@/llave'
import type { ViajeComprado } from '@/tipos'

const { t, locale } = useI18n()
const proximos = ref<ViajeComprado[]>([])

onMounted(async () => {
  const llave = leerLlave()
  if (!llave) return
  try {
    const r = await api.misCompras(llave)
    guardarLlave(r.llave)
    proximos.value = r.futuros
  } catch (e) {
    // Si la llave venció, se quita; si fue un fallo de red, simplemente no se muestra.
    if (e instanceof api.ErrorPublico && e.estado === 401) guardarLlave(null)
  }
})
</script>
