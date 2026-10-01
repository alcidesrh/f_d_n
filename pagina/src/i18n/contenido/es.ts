import type { Contenido } from './tipos'

const es: Contenido = {
  nosotros: {
    titulo: 'Quiénes somos',
    entradilla: 'Desde 1958 conectamos a Guatemala con un servicio de transporte de pasajeros y encomiendas seguro, puntual y cómodo.',
    historia: [
      'La empresa Transportes Fuente del Norte fue fundada en 1958 por un hombre pionero y visionario que, a pesar de las condiciones de las carreteras, no dejó que sus esfuerzos fueran en vano.',
      'Empezó con un pick-up que llevaba pasajeros de la aldea Mariscos al cruce de Trincheras, y con una camionetilla de tres filas que salía de Mariscos a Los Amates y de Mariscos a Puerto Barrios.',
      'En 1964 la empresa adquirió la línea de transporte de Bananera a Guatemala, un paso que exigió mucho esfuerzo y dedicación.',
      'En 1969 abrió brecha hacia el territorio petenero y el área del Atlántico, con las líneas de Guatemala a Puerto Barrios, Guatemala a Santa Elena, Guatemala a Las Cruces y Guatemala a Melchor de Mencos: el fruto del trabajo arduo y tenaz de don Alfredo Mendoza Pellecer. En 1981 acordó con Transportes Litegua las áreas de trabajo: Fuente del Norte se quedó con la línea de Guatemala a Petén y Litegua con la de Guatemala a Puerto Barrios.',
      'Don Alfredo Mendoza siempre pensó en la familia: se preocupó por dejar un legado a sus hijos y formarlos para que llevaran su visión mucho más lejos, como pioneros de las nuevas generaciones.',
      'Esa visión dio origen al servicio de lujo de los autobuses Clase Oro y Maya de Oro. Hoy la empresa se ha extendido dentro y fuera del país, hacia Honduras, El Salvador, Belice y las fronteras con México y Belice.',
      'Cuenta con servicio de lujo en autobuses Clase Oro de doble piso para el sur y el norte del país, y con servicio de Santa Elena a Cobán, con sus respectivos derechos y autorizaciones de la Dirección General de Transportes.',
      'Actualmente está constituida como sociedad anónima bajo el nombre de Transportes Fuente del Norte La Pionera, S.A.',
    ],
    secciones: [
      {
        titulo: 'Base legal',
        parrafos: [
          'Empresa de servicio de transporte de pasajeros y carga inscrita con patente de comercio de sociedad bajo el registro número 42723, folio 373, libro 136 de sociedades, registrada el 31 de enero de 2000. Inscrita ante la Superintendencia de Administración Tributaria (SAT) el 4 de febrero de 2000, con la actividad de transporte de pasajeros y carga por carretera.',
        ],
      },
      {
        titulo: 'Visión',
        parrafos: ['Ser una empresa de transporte de pasajeros y encomiendas a la vanguardia en la aplicación de tecnología, que garantice un servicio de calidad y confort.'],
      },
      {
        titulo: 'Misión',
        parrafos: [
          'Ser líderes en el transporte de pasajeros y encomiendas, nacional e internacional, con un servicio de calidad para nuestros clientes, una fuente de trabajo estable para nuestros empleados y un beneficio para el desarrollo social y económico del país, basados en la eficiencia, la satisfacción del cliente y la mejora continua.',
        ],
      },
      {
        titulo: 'Objetivos',
        items: [
          'Ofrecer el mejor servicio de transporte de pasajeros y encomiendas, de manera segura y confiable.',
          'Velar porque todo el personal aplique las normas y procedimientos de la empresa en todos los servicios.',
          'Garantizar la eficiencia y la seguridad para dar a los clientes el mejor servicio posible.',
        ],
      },
      {
        titulo: 'Estrategias',
        items: [
          'Servicio al cliente: el cliente por encima de todo.',
          'Nuevas unidades de autobuses, para viajar con comodidad, seguridad y confort.',
          'Nuevos servicios: autobuses de lujo con aire acondicionado, televisión y baño, y encomiendas de puerta a puerta.',
        ],
      },
      {
        titulo: 'Valores',
        items: [
          'Honestidad: es el eje de todas nuestras operaciones, con los clientes y con cada persona que aporta su talento a la empresa.',
          'Respeto: por las personas y por los compromisos adquiridos; así ganamos el reconocimiento de la calidad de nuestro servicio.',
          'Trabajo: la fuente primordial de beneficios para todos; el resultado es el único indicador de nuestro esfuerzo.',
          'Tecnología: la aplicamos y desarrollamos para estar a la vanguardia en calidad y servicio.',
        ],
      },
    ],
  },
  servicios: {
    titulo: 'Nuestros servicios',
    entradilla: 'Elija la forma de viajar que mejor se adapte a usted, o envíe sus encomiendas con la experiencia de más de seis décadas.',
    lista: [
      {
        id: 'oro-gran-lujo',
        nombre: 'Clase Oro Gran Lujo',
        resumen: 'Dos pisos, Wi-Fi, dos baños y planta baja ejecutiva con asientos cama de piel.',
        texto: 'La flota Clase Oro Gran Lujo es una forma distinta de viajar por Guatemala: confort, lujo y esparcimiento. Planta alta de 48 asientos todo confort y planta baja ejecutiva con 6 asientos cama de piel, para que usted descanse mientras lo llevamos a su destino.',
        destacados: ['Wi-Fi', 'Dos baños', 'Aire acondicionado', 'Monitores con audífonos', 'Toma eléctrica por asiento', 'Dos pilotos certificados', 'Seguro de viajero', 'Control de velocidad automático'],
      },
      {
        id: 'oro',
        nombre: 'Clase Oro',
        resumen: 'Doble piso, asientos reclinables y 9 asientos semicama en la planta baja.',
        texto: 'Ideales para viajes de negocios o de placer. En la planta alta, asientos reclinables con luz de lectura individual; en la baja, 9 asientos semicama para un viaje más placentero. En Clase Oro su viaje es cómodo y puntual.',
        destacados: ['Aire acondicionado', 'Baño', 'Monitores en ambos niveles', 'Dos pilotos certificados', 'Seguro de viajero', 'Amplias cajuelas de equipaje'],
      },
      {
        id: 'platino',
        nombre: 'Platino',
        resumen: 'Autobuses modernos de primera clase con asientos reclinables.',
        texto: 'El mismo esmerado servicio que nos distingue, en autobuses modernos y confortables de primera clase. Lo llevamos a su destino con prontitud, seguridad y confort.',
        destacados: ['Asientos reclinables', 'Aire acondicionado automatizado', 'Luz de lectura individual', 'Ventanas panorámicas', 'Seguro de viajero', 'Pilotos certificados'],
      },
      {
        id: 'economico',
        nombre: 'Económico',
        resumen: 'De frontera a frontera, cuidando su economía.',
        texto: 'Lo conecta con cualquier punto de Guatemala en autobuses cómodos y seguros. Pensando en las familias chapinas, el servicio Económico le permite viajar cuidando su economía y la de los suyos.',
        destacados: ['Asientos individuales', 'Seguro de viajero', 'Presencia en todo el país'],
      },
      {
        id: 'renta',
        nombre: 'Renta de autobuses y excursiones',
        resumen: 'Excursiones escolares, de negocios, de trabajo o de esparcimiento.',
        texto: 'Los viajes en grupo se vuelven una experiencia agradable con un autobús de Fuente del Norte. Nos ajustamos a su presupuesto: unidades con aire acondicionado y todo servicio, o económicas, según su viaje. Llámenos sin compromiso.',
        destacados: ['A su medida', 'Con o sin aire acondicionado', 'Pilotos profesionales'],
      },
      {
        id: 'encomiendas',
        nombre: 'Encomiendas',
        resumen: 'Paquetes y documentos de estación a estación, el mismo día.',
        texto: 'Envíe paquetes, documentos y todo lo que necesite mandar a los suyos o a su empresa, de forma segura, honesta, rápida y puntual, con el respaldo de más de 60 años de experiencia. Pregunte en cualquier estación.',
        destacados: ['Desde Q25.00', 'Sale el mismo día', 'Se recoge al día siguiente desde las 8:00 a. m.'],
      },
    ],
  },
  politicas: {
    titulo: 'Términos y condiciones',
    entradilla: 'Lo que debe saber antes de viajar con nosotros.',
    secciones: [
      {
        titulo: 'Equipaje',
        parrafos: [
          'Puede transportar 35 libras de equipaje. Si rebasa el peso permitido, tendrá un costo adicional de Q3.00 por libra de exceso.',
          'La empresa no se hace responsable por pérdida o daños de valores, cajas frágiles, equipo de cómputo, electrónico o electrodomésticos: quedan bajo responsabilidad del pasajero.',
        ],
      },
      {
        titulo: 'Forma de pago',
        items: [
          'En nuestras estaciones puede pagar en efectivo o con tarjeta de crédito o débito Visa y Mastercard.',
          'En línea puede pagar con tarjeta de crédito o débito Visa y Mastercard. Las medidas de seguridad las determina el banco emisor (3-D Secure).',
          'Los niños menores de 3 años no pagan asiento; los mayores de 3 años pagan boleto completo.',
          'Todo niño debe viajar en compañía de un adulto.',
        ],
      },
      {
        titulo: 'Compras en línea',
        items: [
          'La venta en línea cierra antes de cada salida (el horario se muestra en cada salida). Después, compre en la estación.',
          'Al pulsar «Pagar asientos» sus asientos quedan apartados unos minutos mientras paga. Si no termina la compra, se liberan solos.',
          'Su boleto en PDF se descarga al pagar y llega a su correo con los datos de la factura electrónica.',
          'Los boletos comprados en línea no se reembolsan ni se modifican en línea.',
        ],
      },
      {
        titulo: 'Cambios de fecha',
        parrafos: [
          'Puede transferir o cambiar la fecha de su boleto hasta 3 horas antes de la salida en cualquier estación de Transportes Fuente del Norte, presentando el boleto original y una identificación oficial vigente de la persona cuyo nombre aparece en él. Si lo hace después, queda a discreción de la empresa reasignarlo.',
        ],
        items: [
          'No es posible reasignar si no hay disponibilidad o los asientos tienen precios distintos.',
          'No es posible reasignar si falta menos de una hora para la salida.',
          'No es posible reasignar a una salida de otra empresa: en las rutas compartidas, las empresas alternan los días.',
        ],
      },
      {
        titulo: 'Políticas de abordaje',
        parrafos: ['Por su seguridad, no se permite:'],
        items: [
          'Abordar en estado de ebriedad o con señales de haber consumido drogas.',
          'Ingresar con armas de fuego, armas blancas u objetos punzocortantes. Si porta alguno, entréguelo al piloto, que se lo devolverá al llegar a su destino.',
          'Ingresar con animales al autobús.',
          'Ingresar bebidas alcohólicas o drogas.',
          'Transportar pólvora o juegos pirotécnicos, explosivos, solventes, tanques de gas, pinturas o corrosivos.',
        ],
      },
      {
        titulo: 'Encomiendas',
        items: [
          'Preséntese en la estación desde donde desea enviar la encomienda.',
          'El costo mínimo por paquete, sobre o documento es de Q25.00; varía según el peso y el volumen (se toma el mayor).',
          'Se registran los datos del remitente, del destinatario y de la encomienda.',
          'El envío sale el mismo día en la última corrida y puede recogerse al día siguiente desde las 8:00 a. m.',
        ],
      },
    ],
  },
}

export default es
