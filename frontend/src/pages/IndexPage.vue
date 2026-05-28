<template>
  <q-page class="q-pa-md">
    <h1 class="text-h4 mb-4">Bienvenido a la Aplicación GraphQL con Quasar</h1>
    <p class="mb-6">
      Seleccione una entidad para gestionar sus datos. Las rutas se generan
      automáticamente a partir del esquema GraphQL introspeccionado.
    </p>
    <q-list bordered class="rounded-borders" v-if="entityRoutes.length > 0">
      <q-item
        v-for="route in list"
        :key="route.path"
        clickable
        tag="a"
        :to="route.path"
      >
        <q-item-section>
          <q-item-label>{{
            route.name || capitalize(route.path.slice(1, -1))
          }}</q-item-label>
          <q-item-label caption
            >Acceder a la gestión de {{ route.path.slice(1, -1) }}</q-item-label
          >
        </q-item-section>
        <q-item-section side>
          <q-icon name="sym_o_arrow_forward" />
        </q-item-section>
      </q-item>
    </q-list>

    <q-banner v-else inline-actions class="text-white bg-negative mt-4">
      No se encontraron entidades disponibles. Verifique la introspección
      GraphQL.
    </q-banner>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";

// Función auxiliar para capitalizar nombres (e.g., 'users' -> 'Users')
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

const router = useRouter();
const entityRoutes = ref([]);

const list = computed(() => {
  let temp = [],
    type;

  for (const key in entities.value) {
    type = entities.value[key];
    if (
      !type.name.endsWith("Connection") &&
      !type.name.endsWith("Edge") &&
      !type.name.endsWith("PageInfo") &&
      !type.name.endsWith("Resource") &&
      !type.name.endsWith("Payload") &&
      !type.name.endsWith("PaginationInfo")
    ) {
      temp.push({
        name: type.name,
        path: `/lista/${type.name.toLowerCase()}`,
        meta: { entity: type.name },
      });
    }
  }
  return temp;
});

onMounted(() => {
  // Filtrar rutas generadas dinámicamente (asumiendo patrón /{entity}s)
  entityRoutes.value = router
    .getRoutes()
    .filter(
      (route) =>
        route.path.endsWith("s") && route.path !== "/" && route.meta?.entity,
    );
});
</script>

<style scoped>
/* Estilos opcionales para responsividad adicional; Quasar maneja la mayoría por defecto */
.q-list {
  max-width: 800px;
  margin: 0 auto;
}
</style>
