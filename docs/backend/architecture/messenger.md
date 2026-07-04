# Message Bus (Messenger)

## Configuración

Archivo: `config/packages/messenger.yaml`

Symfony Messenger está configurado con transporte Doctrine para manejo de mensajes asíncronos:

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    multiplier: 2
            failed: 'doctrine://default?queue_name=failed'
        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: async
            Symfony\Component\Notifier\Message\ChatMessage: async
            Symfony\Component\Notifier\Message\SmsMessage: async
```

## Transportes

| Transporte | DSN | Propósito |
|-----------|-----|-----------|
| `async` | `%env(MESSENGER_TRANSPORT_DSN)%` (Doctrine) | Cola principal de mensajes asíncronos |
| `failed` | `doctrine://default?queue_name=failed` | Mensajes que fallaron después de reintentos |

## Estrategia de reintentos

- Máximo 3 reintentos por mensaje
- Multiplicador de 2x entre reintentos (backoff exponencial)
- Después de agotar reintentos, el mensaje se mueve a `failed`

## Buses

```yaml
buses:
    messenger.bus.default: []
```

Se usa el bus predeterminado de Symfony. No hay buses adicionales configurados.

## Mensajes enrutados

| Mensaje | Transporte |
|---------|-----------|
| `SendEmailMessage` | async (envío de correos) |
| `ChatMessage` | async (notificaciones chat) |
| `SmsMessage` | async (notificaciones SMS) |

## Uso en el proyecto

Actualmente el Messenger se usa principalmente para:
- Envío de correos electrónicos asíncronos
- Notificaciones en segundo plano

Los mensajes se almacenan en la tabla `messenger_messages` de PostgreSQL usando el transporte Doctrine.

## Comandos útiles

```bash
# Consumir mensajes de la cola async
php bin/console messenger:consume async -vv

# Listar mensajes fallidos
php bin/console messenger:failed:show

# Reintentar mensajes fallidos
php bin/console messenger:failed:retry

# Eliminar mensajes fallidos
php bin/console messenger:failed:remove
```
