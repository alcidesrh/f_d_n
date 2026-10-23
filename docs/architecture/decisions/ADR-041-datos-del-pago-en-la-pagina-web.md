# ADR-041: Datos del pago en la página web — dirección por listas y factura a otro nombre

**Estado:** Aceptada (modifica el formulario de pago de ADR-023)

## Contexto

Al comparar el pago de la página nueva con el de la página en producción (`transportesfuentedelnorte.com`, base propia, distinta del legado) salieron tres diferencias. Los datos de la página anterior (38.929 compras completadas, feb-2023 a oct-2026) muestran:

| Dato | Página anterior | Página nueva (antes de este ADR) |
| --- | --- | --- |
| Nacionalidad, tipo y número de documento | No los pide | Los pedía (opcionales) |
| Departamento y ciudad | Listas: país → departamento → ciudad (dataset *countries-states-cities*: 250 países, 4.891 regiones, 146.030 ciudades). El 100 % de las compras los trae elegidos | Texto libre |
| Factura a otro nombre | «La factura irá a nombre de», editable, o un NIT que se busca en la SAT. El 44 % de las compras con CF (9.702 de 22.235) facturó a un nombre distinto del titular | No se podía: el receptor era el comprador o el cliente ya guardado con ese NIT |
| Código postal | Opcional; en Guatemala el valor más repetido es «502» (el código telefónico) | Solo EE. UU. y Canadá |
| Marca de la tarjeta | Se elige (Visa, Mastercard, Cartes Bancaires) | Se deduce del número |

Cartes Bancaires: 1 compra completada de 61 intentos. Guatemala es el 82 % de las compras; las demás vienen de más de 40 países.

## Decisión

1. **Sin documento ni nacionalidad en la web.** El comprador no es necesariamente quien viaja y una compra lleva varios asientos. `Comprador` ya no los lee; el manifiesto del piloto queda sin nacionalidad para los boletos web (como en la página anterior). `/api/publico/catalogos` deja de mandar tipos de documento y naciones.

2. **Dirección de la tarjeta por listas.** Catálogo propio `Region` (país ISO alfa-2, código ISO 3166-2 sin el país, nombre) y `Ciudad`, importado de la base de la página anterior con `app:geografia:importar` (`--origen` o `GEOGRAFIA_ORIGEN_URL`; abre el origen en solo lectura; reemplaza todo en una transacción; nada lo referencia por clave foránea). Las regiones conservan el id del origen. Se depura al importar (`App\Geografia\Depuracion`, pura): sin los sufijos en inglés («Petén Department» → «Petén», «Cayo District», «Alajuela Province», «Provincia de Cartago»; si dos chocarían, conservan el original) y sin los municipios repetidos de Guatemala («Municipio de Flores» se descarta si está «Flores»; si no, se renombra). Resultado: 4.891 regiones y 145.366 ciudades (Guatemala: 22 departamentos, 324 ciudades).
   - API pública: `GET /api/publico/geografia/{pais}/regiones` y `GET /api/publico/geografia/regiones/{id}/ciudades` (cacheables; la página además las recuerda en memoria).
   - Donde el país no tiene regiones (54) o la región no tiene ciudades (1.482), el campo vuelve a texto libre: el cliente nunca queda sin poder pagar.
   - Al banco va el **código** del estado en EE. UU. y Canadá (lo exige para validar la dirección) y el **nombre** en los demás (`regionParaBanco`). La ciudad va con el nombre de la lista.

3. **Factura a otro nombre.** Sección «Factura» arriba de los términos: NIT (CF por defecto) y «A nombre de». Vacío = a nombre del comprador (se ve como placeholder). Con un NIT válido (módulo 11) la página consulta la SAT sola (`GET /api/publico/nit?nit=`, límite propio `publico_nit`: 20 cada 10 min por IP) y llena el nombre con la razón social, sin dejarlo editar. Al pagar, el backend vuelve a consultar y, con NIT, **siempre** usa la razón social de la SAT (no lo que mandó el navegador). El nombre viaja en `Comprador.nombreFactura` (se guarda con el pago) y queda en `BoletoVenta.nombreFactura`; `Facturador` lo usa como receptor si existe. El dashboard de compras web lo muestra y lo deja corregir («Editar»; al cambiar el NIT sin indicar nombre, se borra el anterior).

4. **Sin cambios** en marca de tarjeta (se sigue deduciendo del número; Cartes Bancaires no se agrega: es cobranding de Visa/Mastercard y casi no se usa) ni en código postal (solo EE. UU. y Canadá).

## Consecuencias

- Menos rechazos por direcciones mal escritas; los datos de facturación de la tarjeta quedan homogéneos.
- El cliente ve antes de pagar a nombre de quién sale la factura; el reclamo de la página anterior queda cubierto.
- El endpoint del NIT expone razones sociales de la SAT a cualquiera (como la página anterior); lo acota el límite por IP.
- Los nombres de ciudades del dataset están en inglés cuando el dataset los trae así («Guatemala City»): se corrigen en el origen o ampliando `Depuracion`.
- Para llenar el catálogo en otra base: `bin/console app:geografia:importar --origen='postgres://…'` (la URL no va en git).
