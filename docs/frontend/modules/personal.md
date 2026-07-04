# Módulo Personal

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Piloto` | `id_card` | Pilotos / conductores |
| `Conductor` | `badge` | Conductores adicionales |
| `Empresa` | `home_work` | Empresas de transporte |
| `Usuario` | `person` | Usuarios del sistema (solapa con Seguridad) |

## Páginas

Todas las entidades usan rutas dinámicas CRUD:
```
/lista/Piloto         → DynamicCollection.vue
/form/Piloto/:id?     → DynamicForm.vue
/lista/Empresa        → DynamicCollection.vue
/form/Empresa/:id?    → DynamicForm.vue
```

`Usuario` tiene páginas específicas en `pages/user/`:
```
/usuarios             → UserCollection.vue
/usuarios/crear       → UserForm.vue
/usuarios/edit/:id    → UserForm.vue
/usuarios/cuenta/:id  → UserAccount.vue
```

## Componentes

- `src/components/user/`: UserCreate, UserForm, UserList, UserShow, UserUpdate
- `src/pages/user/`: UserCollection, UserForm, UserAccount

## Almacenes

- `src/stores/user/`: Store específica para gestión de usuarios
- Stores dinámicas para Piloto, Conductor, Empresa

## Notas

- `Piloto` tiene relación con `Empresa` y `Usuario`
- Los formularios de Piloto cargan opciones de Empresa y Usuario dinámicamente
- `Conductor` no está en `schemaStore.editables` pero podría agregarse
