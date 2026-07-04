# Entidades — Personal

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 2

## Piloto

**Tabla**: `piloto`  
**Subdominios**: flota, personal  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |

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

