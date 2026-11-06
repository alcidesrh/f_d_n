# ADR-054: Suplantación de usuarios

**Estado:** Aceptada

## Contexto

Para dar soporte o revisar un problema, el administrador del sistema necesita ver y hacer exactamente
lo que ve y hace un usuario: con sus asignaciones, empresas, estación y agencia. Pedirle la contraseña
no es aceptable, y armar un usuario de prueba con los mismos roles no reproduce su estado.

Todo lo que puede hacer una petición sale del dueño del Bearer (`Autorizacion`, ADR-039) y el firewall
no tiene sesión (ADR-046). El `switch_user` de Symfony obliga a mandar `_switch_user` en cada petición
y no deja una huella por token para auditar.

## Decisión

1. **Un token aparte para entrar como el usuario.** `POST /api/usuarios/{id}/suplantar` crea un
   `ApiToken` del usuario marcado con `suplantadoPor` (el administrador) y el `motivo`, que vence a los
   N minutos (configurable, sin renovación). El login del usuario nunca lo recibe (`Usuario::getToken()`
   filtra los de suplantación). Una suplantación a la vez por administrador.
2. **Reglas en el servidor** (`ReglasSuplantacion`, puras): solo el administrador del sistema
   (`sistema()` y la capacidad `iam.suplantar` para la navegación), nunca sobre otro administrador del
   sistema, ni sobre uno mismo o un usuario inactivo, sin encadenar, y con motivo si se exige. Si el
   administrador es desactivado, su token de suplantación deja de servir.
3. **Lo que nunca se hace como otro** (`BloqueoDuranteSuplantacion`, después del firewall): `/auth`
   (devolvería el token propio del usuario), contraseña, datos y foto de la cuenta, cambios de accesos
   y otra suplantación. Chat y correo según la configuración: permitir, solo lectura (sin escribir,
   sin marcar leído ni aparecer en línea, que se ignoran con 204) o bloquear. El navegador del
   administrador nunca se suscribe a las notificaciones push del usuario.
4. **Auditoría.** Inicio y fin en la bitácora diaria del administrador (siempre, aunque la política
   no lo siga) y en la de accesos del usuario. Cada petición hecha como el usuario se anota siempre,
   con `actividad.suplantado_por_id`, y la línea de tiempo dice quién la hizo de verdad.
5. **Configuración en la base** (`ConfiguracionSuplantacion`, una fila): activa, duración (5 a 480
   min, 60 por defecto), modo del chat (solo lectura por defecto), avisar al usuario en su «Bitácora
   del sistema» del chat (sí) y exigir motivo (sí). Se edita en `/configuracion/suplantacion`.
6. **Frontend.** La sesión guarda la propia en `original`; al entrar y al volver la app se recarga
   para no dejar nada en memoria de la otra identidad. Un 401 suplantando vuelve a la cuenta propia en
   vez de ir al login. Un aviso fijo dice de quién es la cuenta, cuánto queda y «Volver a mi cuenta».
   «Entrar como este usuario» es una acción del listado de usuarios.

## Consecuencias

- Lo que el administrador hace como el usuario queda a nombre del usuario en la bitácora de negocio;
  quién lo hizo de verdad se ve en la bitácora diaria (`request_id` une ambas).
- Las mutaciones GraphQL genéricas no se filtran por ruta: el administrador ya puede hacerlas con su
  propia cuenta, y quedan en la bitácora con quién las hizo.
