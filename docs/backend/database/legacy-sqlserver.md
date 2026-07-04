# Legacy SQL Server

## Visión general

El sistema TerminalOmnibus legacy corre sobre SQL Server 2012. El backend se conecta a esta base de datos para migrar datos al nuevo sistema PostgreSQL.

## 112 entidades legacy

Ubicadas en `src/EntitySistemaFdn/`, todas mapeadas con atributos Doctrine para el entity manager `systemfdn`.

### Catálogos

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Empresa | `empresa` | Empresa de transporte |
| Estacion | `estacion` | Estación/terminal |
| Pais | `pais` | País |
| Nacionalidad | `nacionalidad` | Nacionalidad |
| Departamento | `departamento` | Departamento/región |
| Moneda | `moneda` | Moneda (GTQ, USD) |
| DiaSemana | `dia_semana` | Días de la semana |
| Tiempo | `tiempo` | Tiempos/horarios |
| Sexo | `sexo` | Sexo |
| TipoDocumento | `tipo_documento` | Tipos de documento de identidad |
| TipoDocumentoBoleto | `tipo_documento_boleto` | Tipos de documento para boleto |
| TipoDocumentoEncomienda | `tipo_documento_encomienda` | Tipos de documento para encomienda |

### Flota

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Bus | `bus` | Unidad de transporte |
| MarcaBus | `marca_bus` | Marca del bus |
| TipoBus | `tipo_bus` | Tipo/clase de bus |
| ClaseAsiento | `clase_asiento` | Clase de asiento (A/B) |
| AsientoBus | `bus_asiento` | Asientos por tipo de bus |
| SenalBus | `senal_bus` | Señalización del bus |
| ServicioBus | `servicio_bus` | Servicios disponibles en el bus |
| EstadoBus | `estado_bus` | Estado del bus |
| Piloto | `piloto` | Conductor |

### Rutas y servicios

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Ruta | `ruta` | Ruta entre estaciones |
| RutaEstacionItem | `ruta_estacion_item` | Estaciones intermedias de una ruta |
| Itinerario | `itineario` | Itinerario programado |
| ItinerarioCiclico | `itinerario_ciclico` | Itinerario cíclico |
| ItinerarioEspecial | `itinerario_especial` | Itinerario especial |
| HorarioCiclico | `horario_ciclico` | Horario cíclico |
| Salida | `salida` | Salida/viaje programado |
| SalidaBitacora | `salida_bitacora` | Bitácora de salidas |
| EstadoSalida | `estado_salida` | Estado de salida |

### Boletos y ventas

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Boleto | `boleto` | Boleto de pasaje |
| BoletoBitacora | `boleto_bitacora` | Bitácora de boletos |
| BoletoPaginaTemp | `boleto_pagina_temp` | Página temporal de boleto |
| BoletoPaginaAsientoTemp | `boleto_pagina_asiento_temp` | Asiento temporal de página |
| BoletosTicket | `boletos_ticket` | Ticket de boletos |
| TarifaBoleto | `tarifas_boleto` | Tarifas de boletos |
| Reservacion | `reservacion` | Reservación |
| EstadoBoleto | `estado_boleto` | Estado del boleto |
| EstadoReservacion | `estado_reservacion` | Estado de reservación |

### Financiero

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Factura | `factura` | Factura |
| FacturaEmisor | `factura_emisor` | Emisor de factura |
| FacturaGenerada | `factura_generada` | Factura generada |
| Caja | `caja` | Caja |
| OperacionCaja | `operacion_caja` | Operación de caja |
| CorteVentaTalonario | `corte_venta_talonario` | Corte de venta por talonario |
| CorteVentaTalonarioItem | `corte_venta_talonario_item` | Item de corte de venta |
| Talonario | `talonario` | Talonario de boletos |
| EstadoCaja | `estado_caja` | Estado de caja |
| EstadoCorteVentaTalonario | `estado_corte_venta_talonario` | Estado de corte |
| TipoOperacionCaja | `tipo_operacion_caja` | Tipo de operación de caja |
| TipoPago | `tipo_pago` | Tipo de pago |
| TipoPagoEstacion | `tipo_pago_estacion` | Tipo de pago por estación |
| TipoCambio | `tipo_cambio` | Tipo de cambio |
| TipoTipoCambio | `tipo_tipo_cambio` | Tipo de tipo de cambio |
| CalendarioFacturaFecha | `calendario_factura_fecha` | Calendario fiscal |
| CalendarioFacturaRuta | `calendario_factura_ruta` | Calendario por ruta |

### Encomiendas

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Encomienda | `encomienda` | Encomienda/paquete |
| EncomiendaBitacora | `encomienda_bitacora` | Bitácora de encomiendas |
| EncomiendaRuta | `encomienda_ruta` | Ruta de encomienda |
| TarifaEncomienda | `tarifa_encomienda` | Tarifa de encomienda |
| TarifaEncomiendaDistancia | `tarifa_encomienda_distancia` | Tarifa por distancia |
| TarifaEncomiendaEfectivo | `tarifa_encomienda_efectivo` | Tarifa en efectivo |
| TarifaEncomiendaEspeciales | `tarifa_encomienda_especiales` | Tarifas especiales |
| TarifaEncomiendaPaquetesPeso | `tarifa_encomienda_paquetes_peso` | Tarifa por peso |
| TarifaEncomiendaPaquetesVolumen | `tarifa_encomienda_paquetes_volumen` | Tarifa por volumen |
| TipoEncomienda | `tipo_encomienda` | Tipo de encomienda |
| TipoEncomiendaEspeciales | `tipo_encomienda_especiales` | Tipos especiales |
| EstadoEncomienda | `estado_encomienda` | Estado de encomienda |

### Clientes y crédito

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Cliente | `cliente` | Cliente |
| CreditoAgencia | `credito_agencia` | Crédito de agencia |
| DepositoAgencia | `deposito_agencia` | Depósito de agencia |
| EstadoDeposito | `estado_deposito` | Estado de depósito |
| VoucherAgencia | `voucher_agencia` | Voucher de agencia |
| VoucherEstacion | `voucher_estacion` | Voucher de estación |
| VoucherInternet | `voucher_internet` | Voucher internet |

### Seguridad legacy

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| User | `custom_user` | Usuario del sistema |
| Rol | `custom_rol` | Rol del sistema |
| AutorizacionCortesia | `autorizacion_cortesia` | Autorización de cortesía |
| AutorizacionInterna | `autorizacion_interna` | Autorización interna |
| AutorizacionOperacion | `autorizacion_operacion` | Autorización de operación |
| EstadoAutorizacionOperacion | `estado_autorizacion_operacion` | Estado de autorización |
| TipoAutorizacionOperacion | `tipo_autorizacion_operacion` | Tipo de autorización |

### Otros

| Entidad | Tabla legacy | Descripción |
|---------|-------------|-------------|
| Alquiler | `alquiler` | Alquiler |
| EstadoAlquiler | `estado_alquiler` | Estado de alquiler |
| FechaAlquiler | `fecha_alquiler` | Fecha de alquiler |
| Galeria | `galeria` | Galería de imágenes |
| Imagen | `imagen` | Imagen |
| Impresora | `impresora` | Impresora |
| ImpresoraOperaciones | `impresora_operaciones` | Impresora de operaciones |
| PluginImpresion | `plugin_impresion` | Plugin de impresión |
| Conexiones | `conexiones` | Conexiones |
| ServicioEstacion | `servicio_estacion` | Servicio de estación |
| TelefonoEstacion | `telefono_estacion` | Teléfono de estación |
| CorreoEstacion | `correo_estacion` | Correo de estación |
| Banco | `banco` | Banco |
| CuentaBanco | `cuenta_banco` | Cuenta bancaria |
| Tarjeta | `tarjeta` | Tarjeta |
| TarjetaBitacora | `tarjeta_bitacora` | Bitácora de tarjeta |
| EstadoTarjeta | `estado_tarjeta` | Estado de tarjeta |
| TipoTarjeta | `tipo_tarjeta` | Tipo de tarjeta |
| Log | `log` | Log del sistema |
| LogCode | `log_code` | Código de log |
| LogItem | `log_item` | Item de log |
| Notificacion | `notificacion` | Notificación |
| Job | `job` | Trabajo programado |
| JobSync | `job_sync` | Sincronización de jobs |
| JobTag | `job_tag` | Tag de job |
| Sistema | `sistema` | Configuración del sistema |

## Limitaciones del mapping legacy

1. **Sin claves foráneas explícitas**: Las relaciones legacy no tienen constraints FK en muchos casos.
2. **Codificación ISO-8859-1**: Los datos legacy vienen en Latin-1 y deben convertirse a UTF-8 durante la migración.
3. **Tipos de datos heterogéneos**: SQL Server usa tipos como `money`, `smalldatetime`, `ntext` que requieren mapeo especial.
4. **IDs mixtos**: Algunas tablas usan PK numéricas, otras usan PK string (codigo).
5. **Sin soft-deletes**: Muchas tablas no tienen columna de activo/baja.
