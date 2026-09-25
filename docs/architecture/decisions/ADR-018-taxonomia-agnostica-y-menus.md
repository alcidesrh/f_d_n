# ADR-018: Taxonomía agnóstica (referencia polimórfica) y menús de navegación sobre ella

**Estado:** Aceptada

## Contexto

El negocio necesita expresar jerarquías sobre información del dominio, y la misma información admite varias jerarquías a la vez. El primer caso concreto son los menús de navegación: los mismos ítems navegables (texto + ícono + ruta de vue-router) se organizan en menús distintos según el rol del usuario, cada uno con su propio árbol y su orden.

Modelar cada jerarquía con su propia tabla (`MenuTaxonomia`, `EstacionTaxonomia`, …) duplica la lógica de árbol (orden entre hermanos, mover subárboles, validar ciclos y profundidad) en cada entidad. Una autorreferencia en la entidad (`parent` dentro de `MenuItem`) impide que un mismo ítem esté en dos árboles.

Había además en la base de datos tablas huérfanas de un intento anterior de menús (`menu`, `menu_role`, `menu_menu`, `menu_permiso`, `menu_layout_assignment`, `submenu_order`), sin entidad ni datos, y el enum `LayoutArea` sin uso.

## Decisión

**Taxonomía genérica** (`App\Entity\Taxonomy`, `App\Entity\TaxonomyNode`, módulo `App\Taxonomy`):

- `Taxonomy`: un esquema (árbol ordenado) con `nombre`, `codigo` opcional único, `subjectClass` opcional (si se fija, todos los nodos clasifican esa entidad) y `maxDepth` opcional.
- `TaxonomyNode`: lista de adyacencia (`parent`) + `position` entre hermanos + **referencia polimórfica** `(subjectClass, subjectId)` a cualquier entidad de `App\Entity` (nombre corto, como `EntityConfiguration.entityClass`). Índice único `(taxonomy, subjectClass, subjectId)`: un sujeto aparece como mucho una vez por taxonomía, pero puede estar en muchas taxonomías.
- `TaxonomyTree` (puro): arma el árbol desde filas planas, valida el árbol del cliente (forma, clase, duplicados, profundidad) y poda sujetos inexistentes subiendo a sus hijos.
- `TaxonomyTreeReader`: una consulta para los nodos de N taxonomías + una por clase de sujeto (cargador inyectable para hacer joins, sin N+1).
- `TaxonomyTreeWriter`: reemplaza el árbol entero reutilizando los nodos de los sujetos que siguen (sin violar el índice único a mitad del flush).

**Menús** (`Menu`, `MenuItem`, `MenuPlacement`, módulo `App\Navigation`):

- `MenuItem`: `nombre` (el `label` sigue siendo derivado), `icon` (`Icon`) y `route` (`VueRoute`), todos obligatorios. Se crea antes de usarse en un menú. `position` no vive en el ítem sino en el nodo de cada menú.
- `Menu`: `nombre`, `roles` (ManyToMany `Role`) y su propia `Taxonomy` (`subjectClass = MenuItem`), creada con el menú y oculta del CRUD.
- `MenuPlacement`: menú + área (`LayoutArea`: `sidebar_left`, `sidebar_right`, `topbar_right`, una por slot `menu-content` del shell) + `position`. Un área admite varios menús ordenados; un menú puede estar en varias áreas.
- Visibilidad (`MenuVisibility`): el usuario ve un menú colocado si alguno de sus roles es uno de los roles del menú **o un ascendiente** (`Role.parents`, transitivo) de alguno de ellos. Un menú sin roles no lo ve nadie.
- API: CRUD genérico GraphQL para `Menu` y `MenuItem`; REST en `MenuController`: `GET/PUT /api/menus/{id}/tree`, `GET/PUT /api/menu-layout`, `GET /api/me/menus`. Editar requiere `ROLE_ADMIN` (con `role_hierarchy`) o el permiso plano `menu.read`/`menu.update`.

**Frontend**: `features/menu-builder` (editor con GSAP Draggable sobre un esquema plano con sangría, `outline.ts`), `core/navigation/userMenus` (menús del usuario) y `app/layout/navigation` (render en las tres áreas).

La migración `Version20260925032716` elimina las tablas huérfanas de menús y crea las nuevas.

## Consecuencias

**Positivas:**

- Cualquier entidad nueva puede clasificarse en árboles sin crear tablas ni código de árbol: basta una `Taxonomy` con su `subjectClass`.
- Un mismo registro participa en varias jerarquías independientes (varios menús con los mismos ítems).
- Lecturas con número fijo de consultas, independiente del tamaño del árbol.

**Negativas:**

- `subjectId` no tiene clave foránea: la integridad referencial la cubre la aplicación. Si un sujeto se borra, sus nodos quedan colgados hasta el siguiente guardado del árbol; el lector los ignora y sube a sus hijos. Si se necesita limpieza inmediata, añadir un listener `postRemove` que borre los nodos por `(subjectClass, subjectId)`.
- No se puede hacer `JOIN` DQL directo del nodo al sujeto; se carga por lotes por clase.
- Renombrar una entidad clasificada exige actualizar `taxonomy_node.subject_class` y `taxonomy.subject_class`.
- `MenuItem` no guarda valores de parámetros de ruta: las rutas con parámetros (`/lista/:entity`) no se ofrecen en el editor y se pintan deshabilitadas.
- La ascendencia de roles sale de `Role.parents` (tabla `role_role`), no de `security.yaml` `role_hierarchy`; son dos jerarquías distintas.
