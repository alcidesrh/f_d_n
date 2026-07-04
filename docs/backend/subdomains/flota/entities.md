# Entidades — Flota

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 5

## Asiento

**Tabla**: `asiento`  
**Subdominios**: flota  

---

## Bus

**Tabla**: `bus`  
**Subdominios**: flota  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |
| `int` | string |  | ❌ |

### Relaciones

- **Collection** → `Collection` (OneToMany)

---

## BusMarca

**Tabla**: `busmarca`  
**Subdominios**: flota  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |

---

## Enclave

**Tabla**: `enclave`  
**Subdominios**: flota  

### Relaciones

- **Collection** → `Collection` (OneToMany)

---

## Piloto

**Tabla**: `piloto`  
**Subdominios**: flota, personal  

### Columnas

| Columna | Tipo | PK | Nullable |
|---------|------|----|----------|
| `string` | string |  | ❌ |

---

