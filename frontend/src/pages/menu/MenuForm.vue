<template>
  <div class="card">
    <template v-if="loading">
      <div class="flex flex-col gap-4">
        <Skeleton v-for="i in 6" :key="i" height="3.5rem" />
      </div>
    </template>

    <Message v-else-if="error" severity="error" :closable="false">
      {{ error }}
    </Message>

    <template v-else>
      <FormKit
        type="form"
        v-model="formData"
        :submit-label="isEdit ? 'Actualizar' : 'Crear'"
        :disabled="submitting"
        @submit="onSubmit"
      >
        <Fluid>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
            <FormKit type="InputText" name="nombre" label="Nombre" validation="required" />
            <FormKit type="InputText" name="label" label="Etiqueta" />
            <FormKit
              type="TreeSelect"
              name="referenciaVueRoute"
              label="Ruta"
              :options="routeTreeOptions as any"
              selection-mode="single"
              :auto-expand="true"
              :filter="true"
              filter-placeholder="Buscar ruta..."
              placeholder="Selecciona una ruta"
            />
            <FormKit
              type="Select"
              name="icon"
              label="Icono"
              :options="iconOptions"
              :filter="true"
              show-clear
              placeholder="Selecciona un icono"
            />
            <FormKit type="InputNumber" name="sort" label="Orden" :min="0" />
            <FormKit
              type="MultiSelect"
              name="allowRoles"
              label="Roles permitidos"
              :options="roleOptions"
              :filter="true"
              display="chip"
              placeholder="Selecciona roles"
            />
          </div>

          <div class="md:col-span-2 mt-6">
            <div class="text-sm font-medium mb-2">Asignación de áreas de layout</div>
            <div class="text-xs text-muted-color mb-4">
              Define en qué áreas del layout aparecerá este menú y en qué orden.
            </div>
            <div class="flex flex-col gap-3">
              <div
                v-for="(assignment, index) in layoutAssignments"
                :key="index"
                class="flex items-center gap-3"
              >
                <Select
                  :model-value="assignment.layoutArea"
                  :options="layoutAreaOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Área"
                  class="w-48"
                  @update:model-value="
                    (v: string | null) => {
                      assignment.layoutArea = v;
                    }
                  "
                />
                <InputNumber
                  :model-value="assignment.position"
                  :min="0"
                  placeholder="Posición"
                  class="w-32"
                  @update:model-value="
                    (v: number | null) => {
                      assignment.position = v ?? 0;
                    }
                  "
                />
                <Button
                  icon="pi pi-trash"
                  rounded
                  text
                  severity="danger"
                  @click="removeAssignment(index)"
                />
              </div>
              <Button
                label="Agregar área"
                icon="pi pi-plus"
                severity="secondary"
                size="small"
                class="self-start"
                @click="addAssignment"
              />
            </div>
          </div>

          <div class="md:col-span-2 mt-6">
            <div class="text-sm font-medium mb-2">Jerarquía de menú</div>
            <div class="text-xs text-muted-color mb-4">
              Selecciona menús padre para crear submenús dentro de una jerarquía.
            </div>
            <FormKit
              type="MultiSelect"
              name="parents"
              label="Menús padre"
              :options="menuParentOptions"
              :filter="true"
              display="chip"
              placeholder="Selecciona menús padre (opcional)"
            />
          </div>
        </Fluid>
      </FormKit>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { gql } from "@apollo/client/core";
import { apollo } from "@/lib/apollo";
import { useEntityRegistry } from "@/composables/useEntityRegistry";
import { useToast } from "primevue/usetoast";

defineOptions({ name: "MenuForm" });

const route = useRoute();
const router = useRouter();
const toasts = useToast();
const registry = useEntityRegistry();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const submitting = ref(false);
const error = ref("");

interface LayoutAssignment {
  layoutArea: string | null;
  position: number;
}

const formData = ref<Record<string, unknown>>({
  nombre: "",
  label: "",
  referenciaVueRoute: null,
  icon: null,
  sort: 0,
  allowRoles: [],
  parents: [],
});

const layoutAssignments = ref<LayoutAssignment[]>([]);

interface TreeNode {
  key: string;
  label: string;
  data?: unknown;
  children?: TreeNode[];
}

const routeTreeOptions = ref<TreeNode[]>([]);
const iconOptions = ref<Array<{ label: string; value: string }>>([]);
const roleOptions = ref<Array<{ label: string; value: string }>>([]);
const menuParentOptions = ref<Array<{ label: string; value: string }>>([]);

const layoutAreaOptions = [
  { label: "Sidebar Izquierdo", value: "sidebar_left" },
  { label: "Sidebar Derecho", value: "sidebar_right" },
  { label: "Barra Superior", value: "topbar_right" },
];

function buildRouteTree(vueRoutes: Record<string, unknown>[]): TreeNode[] {
  const map = new Map<string, TreeNode>();
  const roots: TreeNode[] = [];

  for (const vr of vueRoutes) {
    const rawId = String(vr.id ?? "");
    const id = rawId.includes("/") ? rawId.split("/").pop()! : rawId;
    const node: TreeNode = {
      key: id,
      label: String(vr.nombre ?? vr.path ?? ""),
      data: { path: vr.path, vueRouteName: vr.vueRouteName },
    };
    map.set(id, node);
  }

  for (const vr of vueRoutes) {
    const rawId = String(vr.id ?? "");
    const id = rawId.includes("/") ? rawId.split("/").pop()! : rawId;
    const parentRef = vr.vueRoute as Record<string, unknown> | undefined;
    const parentId = parentRef
      ? (() => {
          const raw = String(parentRef.id ?? "");
          return raw.includes("/") ? raw.split("/").pop()! : raw;
        })()
      : null;

    const node = map.get(id);
    if (!node) continue;

    if (parentId && map.has(parentId)) {
      const parent = map.get(parentId)!;
      parent.children = parent.children ?? [];
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  return roots;
}

function extractIdFromIri(iri: unknown): string | null {
  if (!iri) return null;
  if (typeof iri === "string") {
    const match = iri.match(/\/(\d+)$/);
    return match?.[1] ?? iri;
  }
  if (typeof iri === "object") {
    const obj = iri as Record<string, unknown>;
    if (obj.id !== undefined) return String(obj.id);
    if (obj["@id"] !== undefined) {
      const m = String(obj["@id"]).match(/\/(\d+)$/);
      return m?.[1] ?? String(obj["@id"]);
    }
  }
  return String(iri);
}

function toIri(id: string | number | null | undefined, resource: string): string | null {
  if (id === null || id === undefined || id === "") return null;
  return `/api/${resource}/${id}`;
}

async function fetchData(): Promise<void> {
  loading.value = true;
  error.value = "";
  try {
    const treeResult = await apollo.client.query<{
      vueRoutes: { collection: Record<string, unknown>[] };
    }>({
      query: gql`
        {
          vueRoutes {
            id
            nombre
            vueRouteName
            path
            vueRoute {
              id
            }
          }
        }
      `,
    });
    const treeRoutes = treeResult.data?.vueRoutes?.collection ?? [];
    routeTreeOptions.value = buildRouteTree(treeRoutes);

    const iconStore = registry.getEntity("Icon");
    await iconStore.loadFullList();
    iconOptions.value = iconStore.fullList.map((opt) => ({
      label: opt.label,
      value: extractIdFromIri(opt.id) ?? String(opt.id),
    }));

    const roleStore = registry.getEntity("Role");
    await roleStore.loadFullList();
    roleOptions.value = roleStore.fullList.map((opt) => ({
      label: opt.label,
      value: extractIdFromIri(opt.id) ?? String(opt.id),
    }));

    const menuStore = registry.getEntity("Menu");
    await menuStore.loadFullList();
    menuParentOptions.value = menuStore.fullList
      .filter((opt) => {
        if (isEdit.value) {
          return extractIdFromIri(opt.id) !== String(route.params.id);
        }
        return true;
      })
      .map((opt) => ({
        label: opt.label,
        value: extractIdFromIri(opt.id) ?? String(opt.id),
      }));

    if (isEdit.value) {
      const id = route.params.id as string;
      const result = await apollo.client.query<{ menu: Record<string, unknown> }>({
        query: gql`
          query MenuItem($id: ID!) {
            menu(id: $id) {
              id
              nombre
              label
              icon
              sort
              referenciaVueRoute {
                id
              }
              allowRoles {
                id
              }
              parents {
                id
              }
              layoutAssignments {
                id
                layoutArea
                position
              }
            }
          }
        `,
        variables: { id },
      });
      const item = result.data?.menu;
      if (item) {
        formData.value = {
          nombre: item.nombre ?? "",
          label: item.label ?? "",
          referenciaVueRoute: extractIdFromIri(item.referenciaVueRoute),
          icon: extractIdFromIri(item.icon),
          sort: item.sort ?? 0,
          allowRoles: Array.isArray(item.allowRoles)
            ? item.allowRoles.map((r: Record<string, unknown>) => extractIdFromIri(r.id))
            : [],
          parents: Array.isArray(item.parents)
            ? item.parents.map((p: Record<string, unknown>) => extractIdFromIri(p.id))
            : [],
        };
        const assignments = item.layoutAssignments as Array<Record<string, unknown>> | undefined;
        layoutAssignments.value = (assignments ?? []).map((a) => ({
          layoutArea: (a.layoutArea as string) ?? null,
          position: (a.position as number) ?? 0,
        }));
      }
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
  } finally {
    loading.value = false;
  }
}

function addAssignment(): void {
  layoutAssignments.value.push({ layoutArea: null, position: 0 });
}

function removeAssignment(index: number): void {
  layoutAssignments.value.splice(index, 1);
}

async function onSubmit(data: Record<string, unknown>): Promise<void> {
  submitting.value = true;
  error.value = "";
  try {
    const store = registry.getEntity("Menu");
    const payload: Record<string, unknown> = {
      nombre: data.nombre,
      label: data.label || null,
      referenciaVueRoute: toIri(data.referenciaVueRoute as string, "vue_routes"),
      icon: toIri(data.icon as string, "icons"),
      sort: data.sort ?? 0,
      allowRoles: Array.isArray(data.allowRoles)
        ? data.allowRoles.map((r) => toIri(r as string, "roles"))
        : [],
      parents: Array.isArray(data.parents)
        ? data.parents.map((p) => toIri(p as string, "menus"))
        : [],
    };

    let menuResult: Record<string, unknown>;
    if (isEdit.value) {
      payload.id = `/api/menus/${route.params.id}`;
      menuResult = (await store.update(payload)) as Record<string, unknown>;
    } else {
      menuResult = (await store.create(payload)) as Record<string, unknown>;
    }

    const menuId = extractIdFromIri((menuResult as Record<string, unknown>)?.id);
    if (menuId) {
      const menuIri = `/api/menus/${menuId}`;
      const currentAssignments = layoutAssignments.value.filter((a) => a.layoutArea);

      try {
        const existingResult = await apollo.client.query<{
          menuLayoutAssignments: { collection: Array<{ id: string }> };
        }>({
          query: gql`
            query ExistingAssignments($menu: String!) {
              menuLayoutAssignments(menu: $menu) {
                collection {
                  id
                }
              }
            }
          `,
          variables: { menu: menuIri },
        });
        const existing = existingResult.data?.menuLayoutAssignments?.collection ?? [];
        for (const ex of existing) {
          await apollo.client.mutate({
            mutation: gql`
              mutation DeleteAssignment($input: deleteMenuLayoutAssignmentInput!) {
                deleteMenuLayoutAssignment(input: $input) {
                  clientMutationId
                }
              }
            `,
            variables: { input: { id: `/api/menu_layout_assignments/${ex.id}` } },
          });
        }
      } catch {
        // ignore — collection may be empty or not queryable
      }

      for (const assignment of currentAssignments) {
        try {
          await apollo.client.mutate({
            mutation: gql`
              mutation CreateAssignment($input: createMenuLayoutAssignmentInput!) {
                createMenuLayoutAssignment(input: $input) {
                  clientMutationId
                }
              }
            `,
            variables: {
              input: {
                menu: menuIri,
                layoutArea: assignment.layoutArea,
                position: assignment.position ?? 0,
              },
            },
          });
        } catch (e) {
          console.warn("Error creating layout assignment:", e);
        }
      }
    }

    toasts.add({
      severity: "success",
      summary: isEdit.value ? "Menú actualizado" : "Menú creado",
      life: 3000,
    });
    router.push("/lista/Menu");
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
    toasts.add({
      severity: "error",
      summary: "Error",
      detail: error.value,
      life: 5000,
    });
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchData);
</script>
