# ADR-047: Configuración de Forcon y Cybersource en la base

**Estado:** Aceptada

## Contexto

Las credenciales de cada empresa ya estaban en la base (`CredencialFel`, `CredencialPago`, ADR-021). El resto de la configuración del certificador FEL y de la pasarela de pago estaba repartido en archivos:

- `.env` / `.env.prod.local`: `FEL_CERTIFICADOR`, `FEL_FORCON_URL`, `FEL_FORCON_USUARIO`, `FEL_FORCON_CLAVE`, `PAGO_PASARELA`, `CYBERSOURCE_URL`, `CYBERSOURCE_ORG_ID`.
- `config/services.yaml`: los valores por defecto de esas variables y `fel_emisor_en_linea` (las ventas en línea de La Pionera y Maya de Oro se facturan como Transportes Rosita).

Para cambiar cualquiera de ellos había que editar un archivo en el servidor y reiniciar, porque el contenedor de servicios compilado guarda los valores. La clave global de Forcon quedaba además en texto plano en el entorno.

## Decisión

- **Dos entidades de una sola fila** (`id = 1`), como `ConfiguracionPagina` (ADR-023):
  - `ConfiguracionForcon`: `activo` (Forcon real o el certificador simulado), `url`, `usuario` y `claveCifrada` globales (respaldo cuando una empresa no tiene `CredencialFel`; misma cifra que esta) y `emisoresEnLinea` (JSON: NIT de la empresa → NIT del emisor).
  - `ConfiguracionCybersource`: `activo` (Cybersource real o la pasarela simulada), `url` y `orgId` (ThreatMetrix; null = sin huella).
  - Ambas guardan `actualizadaEn` y `actualizadaPor`. No son recursos de la API (la clave nunca sale).
- **Se leen en cada uso** con `Forcon\AjustesForcon` y `Cybersource\AjustesCybersource`, no al construir los servicios. Los workers de FrankenPHP y de Messenger viven mucho tiempo, y así un cambio vale sin reiniciar. Sin fila, rigen los valores por defecto: simulado o simulada, URL de pruebas, sin credencial global, sin emisores en línea y sin huella.
- **La migración `Version20261031120000` las puebla** con lo que había en los archivos: lee las variables del entorno donde corre (con los mismos valores por defecto que tenía `services.yaml`), copia `fel_emisor_en_linea` y cifra la clave global con `APP_SECRET`. No toca filas que ya existan.
- **Se editan en la administración** (caja «Caja y facturación»): «Certificador FEL (Forcon)» (`/configuracion/forcon`) y «Pasarela de pago (Cybersource)» (`/configuracion/cybersource`), con la capacidad `config.proveedores` (una para los dos: es la misma persona la que los configura; `ROLE_ADMIN` y el rol de sistema). REST en `ConfiguracionProveedoresController` (`/api/configuracion-proveedores/{forcon,cybersource}`, GET y PUT, y `POST …/forcon/probar`). La validación vive en `GestionForcon` y `GestionCybersource`, y la usan también `app:fel:configuracion` y `app:pago:configuracion`.
  - La clave global nunca sale: la API dice solo `conClave`. Al guardar, una clave vacía conserva la guardada y un usuario vacío quita la credencial global.
  - Los emisores en línea se eligen entre las empresas con NIT; no puede haber una a nombre de sí misma ni repetida.
  - Cada empresa muestra el estado de su credencial de Forcon: propia, ilegible (se cifró con otro `APP_SECRET`) o ninguna; y su comercio de Cybersource, si tiene.
  - «Probar» consulta un establecimiento en Forcon (sin caché) con la URL y las credenciales guardadas, aunque Forcon esté apagado.
  - Las credenciales por empresa siguen por consola (`app:fel:credencial`, `app:pago:credencial`).
- Se quitan las variables de `.env` y `.env.prod.dist` y los parámetros de `services.yaml`. `FEL_SIMULADO_FALLA` se queda: es solo para probar el certificador simulado.

## Consecuencias

- **Cambiar de certificador o de pasarela, de URL o de Org ID no requiere reiniciar.** La próxima petición o el próximo mensaje usa el valor nuevo.
- **Las variables viejas ya no hacen nada.** Si quedan en `.env.prod.local` u otro entorno, se ignoran después de la migración.
- **El orden del despliegue importa:** la migración tiene que correr junto con el código. Con el código nuevo y sin la tabla, la primera lectura falla.
- Una consulta por clave primaria cada vez que se certifica, se consulta un NIT o se cobra. Doctrine la sirve de su mapa de identidad dentro de la misma petición.
- Las credenciales de cada empresa todavía no se cargan desde la pantalla: solo se ve su estado.
