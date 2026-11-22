# ADR-068: Reconocer al cliente que vuelve a la taquilla

**Estado:** Aceptada

## Contexto

En la taquilla el mismo cliente se registra una y otra vez. La búsqueda (`Clientes::buscar`) exigía que
cada palabra apareciera tal cual: con una letra de más o de menos, un acento o un espacio doble no lo
encontraba, y el alta (`Clientes::crear`) no revisaba nada. En la base de desarrollo, de 55.437 clientes,
6.695 comparten nombre (sin NIT) con otro, 893 comparten teléfono y hay NIT y documentos repetidos.
En la página web el cliente se reconoce por su correo y su dispositivo; en taquilla no hay nada de eso.

## Decisión

### Búsqueda tolerante (`Clientes::buscar`)

- Compara sin acentos, signos ni mayúsculas (`IdentidadCliente::SQL_CLAVE`, índice de trigramas
  `nat_cliente_busqueda`, rehecho con esa expresión).
- Un término numérico busca el NIT por prefijo, el documento sin separadores (`nat_cliente_documento`)
  y, con 8 dígitos o más, el teléfono por sus últimos 8 dígitos (`nat_cliente_telefono`). Un término de
  una palabra con letras y números (pasaporte) busca también el documento.
- Si salen menos de 5, se completan con los de nombre parecido (`pg_trgm`, `word_similarity` ≥ 0,5 con
  el operador `<%`, que usa el mismo índice). Llegan con `parecido: true` y el buscador lo indica.

### Al dar de alta (`Clientes::parecidos`, `POST /api/venta/clientes/parecidos`)

Mientras se escribe, el diálogo de cliente muestra los ya registrados que podrían ser el mismo, con
«Usar este»:

- **Fuerte** (casi seguro la misma persona): mismo NIT, mismo documento, o mismo teléfono con nombre
  parecido (≥ 0,5).
- **Débil**: mismo teléfono con otro nombre (puede ser la familia) o nombre parecido (≥ 0,6; hacen falta
  nombre y apellido, un nombre solo es de cientos).

Con uno fuerte, `POST /api/venta/clientes` responde 409 `cliente_duplicado` con `parecidos`, salvo
`registrarAunqueExista: true`: el diálogo lo manda cuando se marca «Es otra persona». Así protege
también a quien llame a la API sin el diálogo.

No se pone índice único: un NIT de empresa o un teléfono los comparten varias personas, y la
sincronización (ADR-042) puede traer duplicados del legado.

### Datos limpios al guardar (`IdentidadCliente`)

- Nombre y apellido sin espacios de más ni la cola de signos («GARCIA.-»); teléfono con solo dígitos (y
  el «+» del país); documento en mayúsculas sin espacios, guiones ni puntos; correo en minúsculas.
- El DPI (`TipoDocumento` con sigla `DPI`) se valida por el dígito verificador del CUI al escribirlo
  (alta o cambio del número o del tipo): los mal escritos del legado no impiden editar lo demás. No se
  revisan departamento y municipio porque la lista cambia. En el frontend, espejo en
  `core/venta/cui.ts`.
- Con un NIT válido, si la SAT lo registra con otro nombre, el diálogo ofrece «Usar ese nombre».
- El teléfono se pide con «Para reconocerlo cuando vuelva».

## Consecuencias

- Los datos ya guardados no se limpian: se comparan limpios (las expresiones de los índices).
- Unir los duplicados que ya existen (pasar ventas y boletos de uno a otro) queda para después de la
  mudanza del legado: con la sincronización el cliente tiene el mismo id en los dos sistemas.
- Las expresiones SQL de `IdentidadCliente` y las de la migración `Version20261122100000` deben ser
  iguales letra por letra; si no, la búsqueda vuelve a leer la tabla entera.
