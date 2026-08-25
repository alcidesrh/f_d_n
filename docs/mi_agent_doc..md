##### **Contexto**

Consorcio de empresas que brindan un servicio de buses para el transporte de pasajeros en trayectos largos, medianos y cortos. También proveen un servicio secundario de envío de paquetes o encomiendas que son recibidas y entregadas siempre en las estaciones de los trayectos.  Los trayectos pueden ser itinerarios exclusivos de una empresa o pueden estar compartido en cuyo caso las empresas involucradas se alternan los días para dar el servicio. Cada empresa gestiona un flujo de datos independiente propio de la empresa como las finanzas, su inventario de buses, estaciones y empleados. Sin embargo todas operan y gestionan el negocio de la misma manera y no hay procesos personalizados.



##### Objetivo

Implementacion de un sistema fullstack multiusuario que de soporte a la gestion empresarial de la administracion, la logistica y de los procesos operativos de un servicio de buses para el transporte de pasajeros que incluye un servicio secundario de paqueteria o encomienda.



##### Glosario de conceptos y terminos propios del dominio.

- **<mark>enclave</mark>**: cualquier ubicacion geografica de interes. Inmuebles, sitios y emplazamientos geolocalizables relacionados con el negocio.

- **<mark>trayecto</mark>**:  tramos delimitados por dos enclaves. Un trayecto puede estar contenido parcial o totalmente dentro de otro trayecto. Los trayectos son vectoriales en el sentido matematico, quiere decir que si se tiene dos enclave A y B  los trayectos A->B y B->A  son en escencia distintos.

- **<mark>recorrido</mark>**: esta compuesto por un trayecto delimitado por dos estaciones y un timestamp, pasado o futuro, que establece el momento en que un bus comienza a recorrer el trayecto. 

- **<mark>parada</mark>**: enclave dentro de un trayecto donde temporalmente el bus detiene la marcha. Ya sea para bajar o abordar pasajeros o abastecer combustible.

- **<mark>estacion</mark>**: enclaves que cumplen diferentes funciones como venta de boletos.

- <mark>**empresa**</mark>: es el tennant.

- **<mark>boleto</mark>**: recibo de la compra de uno o mas asientos. Contiene informacion sobre el recorrido y es requerido al momento de abordar.

- <mark>**encomienda**</mark>: se refiere a una solicitud aceptada y registrada del servicio de paqueteria

- <mark>**factura**</mark>: la declaracion ante la oficina tributariode los ingresos por servicio

- <mark>**voucher**</mark>: es un boleto o una encomienda no cobrado

- <mark>**cortesia**</mark>: un tipo de voucher.

- <mark>**reasignacion**</mark>: es un cambio de asiento.

- <mark>**anulacion**</mark>: es cuando se invalida la compra de un asiento asi como su registro en la oficina tributaria.

- **<mark>cliente</mark>**: quienes hacen uso de los servicios.

- **<mark>usuario</mark>**: empleado de alguna empresa. Usan el sistema para la venta de boletos o encomiendas, crean el calendarios de recorrido, generan reportes y demas procesos del negocio segun el rol asignado.

- <mark>**agencia**</mark>: un tipo de usuario que representa una entidad externa asociada a una empresa.

- **<mark>manifiesto\<de pasajero, de venta, ...\></mark>**: son reportes que se generan en formato pdf


