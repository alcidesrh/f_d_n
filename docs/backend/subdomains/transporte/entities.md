# Entidades — Transporte

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: 9

## Boleto

**Tabla**: `boleto`  
**Subdominios**: transporte, venta  

---

## Cliente

**Tabla**: `cliente`  
**Subdominios**: transporte, venta  

---

## Factura

**Tabla**: `factura`  
**Subdominios**: transporte, venta  

---

## Recorrido

**Tabla**: `recorrido`  
**Subdominios**: transporte  

### Relaciones

- **Collection** → `Collection` (OneToMany)

---

## RecorridoMatrioska

**Tabla**: `recorridomatrioska`  
**Subdominios**: transporte  

---

## Servicio

**Tabla**: `servicio`  
**Subdominios**: transporte, venta  

### Relaciones

- **Collection** → `Collection` (OneToMany)

---

## Status

**Tabla**: `status`  
**Subdominios**: transporte  

---

## Trayecto

**Tabla**: `trayecto`  
**Subdominios**: transporte  

### Relaciones

- **Collection** → `Collection` (OneToMany)
- **Collection** ↔ `Collection` (ManyToMany)

---

## Venta

**Tabla**: `venta`  
**Subdominios**: transporte, venta  

### Relaciones

- **Collection** → `Collection` (OneToMany)

---

