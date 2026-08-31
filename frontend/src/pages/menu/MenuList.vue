<template>
  <div>
    <Toolbar class="rounded-none border-none! bg-transparent px-2 mb-2">
      <template #start>
        <PageHead title="Menús" />
      </template>
      <template #end>
        <RouterLink to="/menu/crear">
          <Button label="Crear menú" icon="pi pi-plus" size="small" />
        </RouterLink>
      </template>
    </Toolbar>

    <div class="card" style="min-height: 400px">
      <DataTable
        :value="menus"
        :loading="loading"
        row-key="id"
        scrollable
        scroll-height="flex"
        removable-sort
      >
        <Column field="nombre" header="Nombre" sortable />
        <Column field="label" header="Label" sortable />
        <Column field="icon" header="Icono">
          <template #body="{ data }">
            <icon v-if="data.icon" :name="data.icon" />
            <span v-else class="text-surface-400">&mdash;</span>
          </template>
        </Column>
        <Column field="sort" header="Orden" sortable style="width: 80px" />
        <Column header="Acciones" style="width: 100px">
          <template #body="{ data }">
            <RouterLink :to="`/menu/${data.id}/editar`">
              <Button text rounded size="small">
                <template #icon> <icon name="pencil" /></template>
              </Button>
            </RouterLink>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";

defineOptions({ name: "MenuList" });

interface MenuRow {
  id: number;
  nombre: string;
  label: string;
  icon: string | null;
  sort: number | null;
}

const menus = ref<MenuRow[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const base = import.meta.env.VITE_REST_ENDPOINT ?? "http://localhost/api";
    const res = await fetch(`${base}/menus`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    menus.value = (data["hydra:member"] ?? data["member"] ?? []).map(
      (m: Record<string, unknown>) => ({
        id: Number(m.id),
        nombre: String(m.nombre ?? ""),
        label: String(m.label ?? ""),
        icon: (m.icon as string) ?? null,
        sort: (m.sort as number) ?? null,
      }),
    );
  } catch (e) {
    console.error("[MenuList] Error loading menus:", e);
  } finally {
    loading.value = false;
  }
});
</script>
