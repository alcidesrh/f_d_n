# Glosario de Dominio

## Términos del negocio

### Asiento
Unidad individual de asiento dentro de un autobús. Cada asiento tiene un número, una clase (A o B), fila y columna. Los asientos pertenecen a un bus específico (`Asiento`).

### Boleto
Documento que acredita el derecho de un pasajero a ocupar un asiento en un recorrido específico. Contiene información del cliente, asiento, servicio, recorrido y precio. Es la entidad central del sistema de ventas (`Boleto`).

### Bus / Autobús
Vehículo de transporte de pasajeros. Cada bus tiene una marca, modelo, placa, capacidad de asientos y clase asociada. Pertenece a la flota de la empresa (`Bus`).

### BusMarca / Marca de Bus
Fabricante o marca de los autobuses (ej: Mercedes-Benz, Volvo, Scania). Se usa para clasificar la flota (`BusMarca`).

### Cliente
Persona física o jurídica que adquiere boletos o servicios de transporte. Puede ser un pasajero individual o una empresa contratante (`Cliente`).

### Empresa
Entidad jurídica que opera dentro del sistema FDN. Representa las diferentes compañías del grupo o concesionarias (`Empresa`).

### Enclave
Punto geográfico relevante en la red de transporte. Es la clase base para estaciones y paradas. Contiene nombre, dirección y coordenadas (`Enclave`).

### Estación
Terminal de autobuses donde los pasajeros abordan o descienden. Es un tipo de enclave con personal, ventanillas de venta y servicios (`Estacion`).

### Localidad
Ciudad, pueblo o población atendida por la red de rutas de FDN. Las estaciones y paradas se ubican dentro de localidades (`Localidad`).

### Nación
País donde opera la empresa. Actualmente Guatemala, pero el modelo soporta expansión regional (`Nacion`).

### Parada
Punto de ascenso/descenso intermedio, con menos infraestructura que una estación. Es un tipo de enclave (`Parada`).

### Piloto
Conductor de autobús. Registra datos personales, número de licencia, fecha de vencimiento, DPI, seguro social y código interno (`Piloto`).

### Recorrido
Trayecto específico que realiza un bus en un horario determinado. Incluye el trayecto, el bus asignado, la empresa operadora y los precios por clase (A/B). Puede contener sub-recorridos (`Recorrido`).

### RecorridoMatrioska
Relación de sub-recorridos dentro de un recorrido principal. Permite modelar rutas que se dividen o combinan en el camino (`RecorridoMatrioska`).

### Servicio
Tipo de servicio de transporte ofrecido (ej: regular, ejecutivo, directo). Los boletos se emiten contra un servicio específico (`Servicio`).

### Trayecto
Ruta entre dos enclaves (origen y destino). Incluye distancia en kilómetros y duración estimada. Los trayectos pueden tener sub-trayectos y son la base para definir recorridos (`Trayecto`).

### Venta
Transacción comercial que agrupa uno o más boletos emitidos a un cliente en un mismo acto de compra. Se asocia a un enclave (estación) donde se realizó la venta (`Venta`).

## Términos de seguridad

### Action / Acción
Operación atómica sobre una entidad (ej: `boleto.crear`, `boleto.anular`, `usuario.ver`). Es la unidad mínima de permiso en el sistema (`Action`).

### Permiso
Agrupación de acciones relacionadas (ej: "Gestión de Boletos" contiene acciones de CRUD sobre boletos). Se asigna a roles o directamente a usuarios (`Permiso`).

### Role / Rol
Perfil predefinido que agrupa permisos. Los roles tienen jerarquía: `ROLE_SUPER_ADMIN → ROLE_ADMIN → ROLE_OPERADOR → ROLE_CONSULTA`. Los roles también pueden heredar de otros roles padres (`Role`).

### ApiToken
Token de acceso a la API. Permite autenticación sin sesión para integraciones externas y el frontend SPA (`ApiToken`).

### Usuario
Persona que utiliza el sistema. Tiene username, contraseña, roles asignados, permisos directos y acciones denegadas (`Usuario`).

## Términos técnicos

### Dynamic CRUD
Sistema que genera automáticamente interfaces de listado y formulario para cualquier entidad registrada, basado en metadatos del backend.

### Entity Configuration
Metadatos de configuración que describen cómo se deben mostrar y editar las entidades en el frontend (campos visibles, orden, tipos de input).

### Flat Permission Set
Modelo de autorización donde los permisos se resuelven como un conjunto plano de acciones, combinando herencia de roles, concesiones directas y denegaciones.

### FrankenPHP
Servidor de aplicación PHP que embebe PHP 8.4 directamente en Caddy, eliminando la necesidad de PHP-FPM. Soporta worker mode y Mercure integrado.

### Mercure
Protocolo de Server-Sent Events (SSE) para actualizaciones en tiempo real. Integrado como módulo de Caddy en el backend.

### Store Factory
Función que genera stores de Pinia dinámicamente en runtime para cualquier entidad, proporcionando operaciones CRUD via GraphQL.

### Subdominio
Contexto delimitado (bounded context) que agrupa entidades y lógica de negocio relacionada: Transporte, Flota, Venta, Personal, Configuración, Infraestructura, Seguridad.

### TerminalOmnibus
Sistema legado basado en Symfony 2.2 y SQL Server 2012 que FDN Transportes reemplaza. Contiene los datos históricos de operación.

### Worker Mode
Modo de operación de FrankenPHP donde el kernel de Symfony permanece en memoria entre peticiones, eliminando el cold boot en cada request.
