# Walkthrough: Sistema de Layout y Páginas en Vue 3 + Vue Router

Hemos implementado un sistema modular, extensible y de tipado estricto para el manejo de layouts y rutas en la aplicación Vue 3.

## Cambios Implementados

### 1. Tipos y Meta de Rutas (`src/types/vue-router.d.ts`)
- Extensión del módulo `vue-router` para incluir metadata tipada en las rutas:
  - `layout`: `'default' | 'auth' | 'blank' | 'formdemo'`
  - `title`: Título de la página
  - `subtitle`: Subtítulo contextual
  - `crumbs`: Lista de migas de pan (breadcrumbs)
  - `requiresAuth`: Control de acceso por autenticación

### 2. Componente Envoltorio de Layouts (`AppLayout.vue`)
- [AppLayout.vue](file:///home/alcides/f_d_n/frontend/src/components/layout/AppLayout.vue): Componente dinámico que evalúa la propiedad `route.meta.layout` e inyecta la página activa dentro del layout seleccionado sin desmontar elementos innecesariamente.

### 3. Sistema de Layouts (`src/layouts/`)
- [default.vue](file:///home/alcides/f_d_n/frontend/src/layouts/default.vue): Layout principal con cabecera (`AppHeader`), barra lateral de navegación colapsable (`SidebarLeft`), área de contenido principal y panel derecho opcional.
- [auth.vue](file:///home/alcides/f_d_n/frontend/src/layouts/auth.vue): Layout moderno para autenticación con gradientes ambientales, contenedor *glassmorphism*, branding y selectores de tema.
- [blank.vue](file:///home/alcides/f_d_n/frontend/src/layouts/blank.vue): Layout minimalista sin barras de navegación para lienzos completos o pantallas de error.
- [formdemo.vue](file:///home/alcides/f_d_n/frontend/src/layouts/formdemo.vue): Layout con panel derecho de inspección.

### 4. Nuevas Páginas y Vistas
- [LoginPage.vue](file:///home/alcides/f_d_n/frontend/src/pages/auth/LoginPage.vue): Vista de inicio de sesión con PrimeVue / FormKit, contraseña con máscara interactiva, recordatorio de sesión y botones de demostración rápida.
- [NotFoundPage.vue](file:///home/alcides/f_d_n/frontend/src/pages/errors/NotFoundPage.vue): Página de error 404 estilizada con botones para regresar o ir al inicio.

### 5. Configuración de Rutas y Guards (`src/router/index.ts`)
- Configuración centralizada de rutas con asignación de layouts.
- Guardia `beforeEach` global que actualiza dinámicamente `document.title` en cada cambio de vista (ejemplo: `Resumen operativo | FDN`).

### 6. Composable de Layout (`src/composables/useLayout.ts`)
- Composable helper para inspeccionar o cambiar dinámicamente el layout, título y migas de pan desde cualquier componente.

---

## Verificación

- **Build de producción**: `npm run build-only` compiló exitosamente todos los módulos en `dist/` (4.6s, 0 errores).
- **Formateador**: `npm run format` ejecutado sobre el directorio `src/`.
