# Arquitectura del Frontend — FDN Transportes

## Vista General

El frontend de FDN Transportes es una **SPA (Single Page Application)** construida con **Quasar 2** sobre **Vue 3.5**, utilizando **TypeScript** en modo estricto. La aplicación se ejecuta en modo `spa` con `vueRouterMode: 'history'`.

```mermaid
C4Context
  title Diagrama de Componentes — Frontend FDN

  Person(usuario, "Usuario", "Operador del sistema de transporte")

  System_Boundary(frontend, "Frontend SPA") {
    Container(quasar, "Quasar 2", "Vue 3.5, TypeScript", "Framework UI y CLI")
    Container(router, "Vue Router 4", "History mode", "Enrutamiento")
    Container(pinia, "Pinia 3", "Persistencia vía plugin-persistedstate", "Estado global")
    Container(apollo, "Apollo Client 4", "GraphQL", "Capa primaria de datos")
    Container(rest, "ofetch", "REST", "Capa secundaria (auth, config)")
    Container(uno, "UnoCSS", "presetWind4 + presetAttributify", "Estilos utilitarios")
    Container(formkit, "FormKit 2", "Con theme personalizado", "Formularios dinámicos")
    Container(i18n, "vue-i18n 11", "Carga perezosa", "Internacionalización")
    Container(mercure, "Mercure", "EventSource", "Tiempo real")
    Container(gsap, "GSAP 3", "Animaciones", "Motion design")
  }

  System_Ext(backend, "Backend Symfony 8", "API Platform, GraphQL, REST, Mercure Hub")
  System_Ext(db, "PostgreSQL 16", "Base de datos principal")

  usuario --> quasar : Interactúa
  quasar --> router : Navegación
  quasar --> pinia : Estado
  apollo --> backend : GraphQL queries/mutations
  rest --> backend : REST endpoints
  mercure --> backend : Server-Sent Events
  backend --> db : Persistencia
```

## Estructura de directorios

```
frontend/src/
├── boot/           # Archivos de arranque (orden secuencial)
├── components/     # Componentes Vue auto-importados
│   ├── admin/      # Componentes de administración
│   ├── crud/       # DynamicCollection, DynamicForm
│   ├── dynamic/    # MenuLarge, MenuMini
│   ├── sidebar/    # SectionTree, SectionMini
│   ├── preload/    # Skeletons de carga
│   ├── permiso/    # CRUD de permisos
│   ├── role/       # CRUD de roles
│   └── user/       # CRUD de usuarios
├── composables/    # Lógica reutilizable (Composition API)
├── config/         # Configuración (endpoints, breakpoints, iconos)
├── css/            # Estilos SCSS globales
├── form/           # FormKit: theme, inputs, schemas, plugins
├── graphql/        # Apollo links personalizados
├── i18n/           # Traducciones (carga perezosa)
├── layouts/        # MainLayout con sidebar, topbar, etc.
├── pages/          # Páginas por módulo
├── router/         # Configuración de rutas
├── services/       # Servicios (API, EventBus, Responsive, StaticData)
├── stores/         # Pinia stores
│   ├── autoimport/ # Stores globales auto-importados
│   └── storeFactory.ts  # Fábrica dinámica de stores
├── types/          # Tipos TypeScript
└── utils/          # Utilidades (colores, reglas UnoCSS, helpers)
```

## Componentes clave del diagrama C4

### Boot sequence
Los 10 archivos de `src/boot/` se ejecutan en orden definido en `quasar.config.ts`: unocss → api-rest → apollo → server-response-listener → formkit → middleware → static-data-gateway → i18n → responsive → gsap.

### Layout
`MainLayout2.vue` (y su variante `MainLayout.vue`) es el layout principal. Estructura: Topbar → SidebarLeft → q-page-container (RouterView) → SidebarRight. El sidebar izquierdo admite modos `large`, `mini`, `onhover` y `close`.

### Router
Tres tipos de rutas:
1. **Rutas dinámicas**: `/lista/:entity` y `/form/:entity/:id?` renderizan `DynamicCollection.vue` y `DynamicForm.vue`
2. **Rutas estáticas**: `/admin/*`, `/usuarios/*`, `/roles/*`, `/venta/boleto`, etc.
3. **Rutas públicas**: `/login`, `/forbidden`

### Stores
- **Schema Store**: Almacena metadatos de entidades (fields, queries, mutations) obtenidos vía introspection de GraphQL
- **Session Store**: Manejo de autenticación, JWT, permisos planos
- **Apollo Store**: Cliente Apollo global
- **Loading Store**: Contador de operaciones en curso con prioridades
- **Sidebar/Menu Stores**: Estado de navegación persistido

### Dynamic CRUD
El sistema de CRUD dinámico es el corazón de la aplicación. `entityRegistry.ts` orquesta la creación de stores en tiempo de ejecución mediante `storeFactory.ts`, que construye stores Pinia con operaciones CRUD completas usando `gql-query-builder`.

### Capa de datos
- **GraphQL** (primaria): Apollo Client 4 con cadena de links: removeTypename → queryLink → mutationLink → authLink → errorLink → loadingLink → httpLink
- **REST** (secundaria): ofetch para auth (`/me/permissions`) y configuración (`/entity_configurations`, `/config-versions`)
- **Mercure**: Tiempo real vía EventSource para actualizaciones de entidades y errores del servidor

### Formularios
FormKit 2 con theme personalizado en `src/form/formkit-theme-fdn/`. Los schemas de formularios se generan dinámicamente desde la configuración de entidad obtenida vía REST, y los inputs se mapean según el tipo GraphQL del campo.

### Estilos
UnoCSS con `presetWind4` y `presetAttributify`. Sistema de colores OKLCH con 17 matices y 11 escalas definidos en `src/utils/colors.ts`. Reglas personalizadas para la escala tipográfica Utopia en `src/utils/unocss_rules.ts`.
