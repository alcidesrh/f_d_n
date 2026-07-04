# Entidades — Seguridad

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 4

## Action

**Tabla**: `action`  
**Subdominios**: seguridad  

### Relaciones

- **Collection** ↔ `Collection` (ManyToMany)
- **Collection** ↔ `Collection` (ManyToMany)

---

## ApiToken

**Tabla**: `apitoken`  
**Subdominios**: seguridad  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |

---

## Role

**Tabla**: `role`  
**Subdominios**: seguridad  

---

## Usuario

**Tabla**: `usuario`  
**Subdominios**: personal, seguridad  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |

### Relaciones

- **Collection** → `Collection` (OneToMany)
- **Collection** → `Collection` (OneToMany)
- **Collection** ↔ `Collection` (ManyToMany)
- **Collection** ↔ `Collection` (ManyToMany)

---

