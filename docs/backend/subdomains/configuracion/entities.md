# Entidades — Configuracion

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 3

## CollectionFieldConfig

**Tabla**: `collectionfieldconfig`  
**Subdominios**: configuracion  

### Relaciones

- **EntityConfiguration** → `EntityConfiguration` (ManyToOne)
  - FK: `EntityConfiguration_id`

---

## EntityConfiguration

**Tabla**: `entityconfiguration`  
**Subdominios**: configuracion  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string | ✅ | ❌ |

---

## FormFieldConfig

**Tabla**: `formfieldconfig`  
**Subdominios**: configuracion  

### Relaciones

- **EntityConfiguration** → `EntityConfiguration` (ManyToOne)
  - FK: `EntityConfiguration_id`

---

