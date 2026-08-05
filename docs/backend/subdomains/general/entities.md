# Entidades — General

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 7

## LayoutProfile

**Tabla**: `layoutprofile`  
**Subdominios**: general  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `bool` | string |  | ❌ |

---

## LayoutProfileRole

**Tabla**: `layoutprofilerole`  
**Subdominios**: general  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `int` | string |  | ❌ |

---

## LayoutProfileUsuario

**Tabla**: `layoutprofileusuario`  
**Subdominios**: general  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `int` | string |  | ❌ |

---

## LayoutSchema

**Tabla**: `layoutschema`  
**Subdominios**: general  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `LayoutArea` | string |  | ❌ |

---

## LayoutSchemaItem

**Tabla**: `layoutschemaitem`  
**Subdominios**: general  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `int` | string |  | ❌ |

---

## Permiso

**Tabla**: `permiso`  
**Subdominios**: general  

---

## VueRoute

**Tabla**: `vueroute`  
**Subdominios**: general  

### Relaciones

- **Collection** → `Collection` (OneToMany)
- **Collection** ↔ `Collection` (ManyToMany)

---

