# Componentes de Autenticación

## LoginPage.vue

**Archivo**: `src/pages/auth/LoginPage.vue`

Página de inicio de sesión. Ruta: `/login` (pública, meta `public: true`).

**Props**: Ninguna.

**Slots**: Ninguno.

**Comportamiento**:
- Formulario de login con campos usuario y contraseña
- Usa `session.login(username, password)` que hace fetch a `POST /login`
- Tras autenticación exitosa, redirige a `session.redirectTo` o `/`
- Muestra errores de autenticación via Quasar Notify

**Flujo**:
1. Usuario ingresa credenciales
2. `session.login()` envía POST a `/login` con credenciales
3. El backend retorna JWT (almacenado en `session.token`)
4. `session.fetchSession()` obtiene permisos de `/me/permissions`
5. Redirige a ruta guardada o dashboard

## ChangePasswordModal.vue

**Archivo**: `src/components/ChangePasswordModal.vue`

Modal para cambio de contraseña.

**Props**:
- `modelValue`: boolean que controla visibilidad del modal

**Eventos**:
- `update:modelValue`: cambio de visibilidad

**Slots**: Ninguno.

**Comportamiento**:
- Formulario con contraseña actual, nueva y confirmación
- Validación de coincidencia de contraseñas
- Submit vía REST al backend
- Cierra modal al completar

## Session Store

La autenticación se gestiona en `src/stores/autoimport/session.ts`:

**Estado persistido**: `user`, `token`, `permissions`
**Métodos**: `login()`, `fetchSession()`, `fetchPermissions()`, `clear()`, `can()`, `canAny()`, `canAll()`

## Protección de rutas

El middleware de navegación (`src/boot/middleware.ts`) verifica:
1. `session.isAuthenticated`: si es false, redirige a `/login`
2. `meta.requiresPermission`: si está definido, verifica con `session.can()`
3. Rutas públicas (`meta.public: true`) no requieren verificación
