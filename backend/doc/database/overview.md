# Modelo de Base de Datos y Entidades

Este documento abstrae el modelo de datos analizando las entidades de Symfony.

## Entidades Detectadas

### Action

- Atributos principales: `roles`, `permisos`

### ApiToken

- Atributos principales: `token`

### Asiento

### Base

### BoletoBase

### LogBase

### NombreNotaStatusBase

### NombreNotaStatusBaseSuperClass

### PersonaBase

### TimeLegacyStatusBase

### TimeStatusBase

### Boleto

### Bus

- Atributos principales: `asientos`, `codigo`, `anoFabricacion`

### BusMarca

- Atributos principales: `nombre`

### Cliente

### CollectionFieldConfig

### EntityConfiguration

- Atributos principales: `collectionFieldConfig`, `formFields`

### FieldConfig

### FormFieldConfig

### Precio

- Atributos principales: `monto`, `moneda`

### Empresa

- Atributos principales: `buses`, `ventas`

### Enclave

- Atributos principales: `ventas`

### Estacion

### Factura

### Icon

### Localidad

### Nacion

### Parada

### Permiso

- Atributos principales: `roles`, `parents`, `children`, `actions`

### Piloto

- Atributos principales: `codigo`

### Recorrido

- Atributos principales: `subrecorridos`

### RecorridoMatrioska

### Role

- Atributos principales: `parents`, `children`, `permisos`, `actions`

### Servicio

- Atributos principales: `boletos`

### Status

### Trayecto

- Atributos principales: `trayectosHijos`, `trayectosPadres`, `recorridos`

### Usuario

- Atributos principales: `username`, `fullName`, `apiTokens`, `userRoles`, `permisos`, `directActions`, `deniedActions`, `ventas`

### Venta

- Atributos principales: `boletos`
