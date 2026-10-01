<!--
  Dashboard de la página web pública (ADR-023): compras en línea (filtro y
  calculadora), configuración de la venta en línea y mensajes de contacto.
  Permiso `pagina.administrar`.
-->
<template>
  <div class="flex flex-col gap-4">
    <Toolbar>
      <template #start><PageHead /></template>
      <template #end>
        <a href="/pagina/" target="_blank" rel="noopener" class="no-underline">
          <Button label="Ver la página" severity="secondary" text size="small"><template #icon><icon name="external-link" class="mr-1" /></template></Button>
        </a>
      </template>
    </Toolbar>

    <Tabs v-model:value="pestana">
      <TabList>
        <Tab value="compras"><icon name="receipt" class="mr-1.5" />Compras</Tab>
        <Tab value="configuracion"><icon name="settings" class="mr-1.5" />Configuración</Tab>
        <Tab value="mensajes">
          <icon name="mail" class="mr-1.5" />Mensajes
          <Badge v-if="sinLeer" :value="sinLeer" severity="danger" class="ml-1.5" />
        </Tab>
      </TabList>
      <TabPanels class="!px-0">
        <TabPanel value="compras"><ComprasPanel /></TabPanel>
        <TabPanel value="configuracion"><ConfiguracionPanel /></TabPanel>
        <TabPanel value="mensajes"><MensajesPanel @sin-leer="sinLeer = $event" /></TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
import { fetchMensajes } from '@/core/pagina-web/api'
import ComprasPanel from './ComprasPanel.vue'
import ConfiguracionPanel from './ConfiguracionPanel.vue'
import MensajesPanel from './MensajesPanel.vue'

const route = useRoute()
const router = useRouter()
const pestana = ref(String(route.query.pestana ?? 'compras'))
const sinLeer = ref(0)

watch(pestana, (p) => void router.replace({ query: { ...route.query, pestana: p } }))
onMounted(async () => {
  sinLeer.value = (await fetchMensajes().catch(() => null))?.sinLeer ?? 0
})
</script>
