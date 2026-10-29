/** Para todos: entrar, moverse por la pantalla y la cuenta propia. */
import type { Guia } from '@/core/ayuda/tipos'

export const guiasEmpezar: Guia[] = [
  {
    id: 'entrar',
    tema: 'empezar',
    titulo: 'Entrar al sistema',
    resumen: 'Usuario y contraseña, en la dirección de la aplicación.',
    para: [],
    pasos: [
      'Abra la dirección de la aplicación en el navegador (o la app instalada en el teléfono).',
      'Escriba su usuario y su contraseña y pulse «Entrar».',
      'Si estaba en otra pantalla al salirse, el sistema lo devuelve a ella.',
    ],
    ojo: [
      'Si la contraseña no funciona, pida a quien administra los usuarios que le ponga una nueva (vea «Poner otra contraseña a un usuario»).',
      'Un usuario desactivado no puede entrar aunque su contraseña sea correcta.',
    ],
    palabras: ['login', 'ingresar', 'iniciar sesion', 'acceso', 'usuario y clave'],
  },
  {
    id: 'pantalla-por-dentro',
    tema: 'empezar',
    titulo: 'La pantalla por dentro',
    resumen: 'Qué hay en la cabecera, el menú lateral y las migas de pan.',
    para: [],
    pasos: [
      'Botón de menú (las tres líneas, arriba a la izquierda): abre o cierra el menú lateral. En el teléfono aparece como un cajón que se abre desde el borde.',
      'Migas de pan (en el centro de la cabecera): muestran por dónde llegó a la pantalla actual. Cada una lo lleva de vuelta.',
      'Reloj (arriba a la izquierda): la hora del sistema.',
      'Botones de la derecha: apariencia (paleta), mensajes, ayuda (signo de pregunta), autorizaciones (campana), pantalla completa y su nombre de usuario.',
      'Si la pantalla tiene un panel lateral derecho, el botón de la extrema derecha lo muestra u oculta.',
    ],
    ojo: [
      'El menú solo muestra las pantallas que puede abrir. Si busca una y no está, vea «No veo una opción o no me deja hacer algo».',
      'El número sobre los mensajes indica conversaciones sin leer; el de la campana, autorizaciones que esperan una respuesta.',
    ],
    palabras: ['cabecera', 'menu', 'barra', 'migas', 'breadcrumb', 'panel derecho'],
  },
  {
    id: 'estacion-de-trabajo',
    tema: 'empezar',
    titulo: 'Elegir mi estación de trabajo',
    resumen: 'Dónde está operando hoy: ahí queda registrado lo que vende, chequea o recibe.',
    para: [],
    antes:
      'La estación venía fija en su usuario; para trabajar en otra había que pedir que se la cambiaran.',
    pasos: [
      'Si puede trabajar en varias estaciones, el sistema le pide elegir una al entrar.',
      'Para cambiarla: toque el botón con el pin de ubicación en la cabecera (muestra el nombre de la estación actual), o su nombre de usuario → «Estación de trabajo».',
      'Elija la estación de la lista (puede escribir para buscarla).',
    ],
    ejemplo: {
      caso: 'Cubre la taquilla de Santa Elena en la mañana y la de Poptún en la tarde.',
      pasos: [
        'Al entrar elige Santa Elena y vende normalmente.',
        'Al llegar a Poptún cambia la estación desde la cabecera antes de la primera venta.',
        'Las ventas de la tarde salen en los reportes de Poptún.',
      ],
    },
    ojo: [
      'Si solo tiene una estación, queda fija y el botón no se abre.',
      '«Más tarde» deja la elección para después; lo que pide estación (vender, chequear, recibir encomiendas) espera a que elija.',
      'Si falta una estación en la lista, pida a quien administra los usuarios que se la incluya.',
    ],
    pantallas: ['venta', 'salidas-chequeo', 'encomienda-registrar', 'mi-cuenta'],
    palabras: ['estacion', 'sucursal', 'taquilla', 'cambiar de estacion', 'ubicacion'],
  },
  {
    id: 'cambiar-mi-contrasena',
    tema: 'empezar',
    titulo: 'Cambiar mi contraseña',
    resumen: 'La cambia usted mismo, sin pedírsela a nadie.',
    para: [],
    pasos: [
      'Toque su nombre de usuario (arriba a la derecha) → «Mi cuenta».',
      'En «Cambiar contraseña» escriba la actual y dos veces la nueva.',
      'Pulse «Cambiar contraseña».',
    ],
    ojo: ['¿Olvidó la actual? Pida a quien administra los usuarios que le ponga una nueva.'],
    abrir: { etiqueta: 'Abrir Mi cuenta', destino: { name: 'mi-cuenta' } },
    pantallas: ['mi-cuenta'],
    palabras: ['clave', 'password', 'contrasena olvidada'],
  },
  {
    id: 'mi-perfil',
    tema: 'empezar',
    titulo: 'Mi foto, mis datos y los avisos',
    resumen: 'Foto para el chat, datos de contacto y avisos de mensajes con la app cerrada.',
    para: [],
    pasos: [
      'Abra «Mi cuenta» desde su nombre de usuario.',
      '«Cambiar foto»: elija una imagen (JPG, PNG o WebP de hasta 2 MB).',
      '«Datos personales»: corrija nombre, correo o teléfono y pulse «Guardar cambios».',
      '«Notificaciones»: actívelas en cada navegador o teléfono donde quiera recibir los mensajes del chat.',
    ],
    ojo: [
      'Las notificaciones son propias de cada navegador: si cambia de computadora, actívelas de nuevo.',
      'Al cerrar sesión, el navegador deja de recibir avisos de su cuenta.',
    ],
    abrir: { etiqueta: 'Abrir Mi cuenta', destino: { name: 'mi-cuenta' } },
    pantallas: ['mi-cuenta'],
    palabras: ['perfil', 'avatar', 'notificaciones', 'push'],
  },
  {
    id: 'apariencia',
    tema: 'empezar',
    titulo: 'Tema claro u oscuro y colores',
    resumen: 'La apariencia de la aplicación en su equipo.',
    para: [],
    pasos: [
      'Pulse el botón de la paleta, en la cabecera: se abre «Personalizar apariencia».',
      'Elija el modo claro u oscuro y, si lo desea, los colores.',
    ],
    ojo: [
      'Al entrar por primera vez el modo sigue el del sistema operativo.',
      'La apariencia es de cada navegador; no cambia la de otros usuarios.',
    ],
    palabras: ['tema', 'modo oscuro', 'colores', 'dark', 'personalizar'],
  },
  {
    id: 'instalar-la-app',
    tema: 'empezar',
    titulo: 'Instalar la aplicación en el teléfono',
    resumen: 'Se usa como una app, a pantalla completa y con la mensajería a mano.',
    para: [],
    pasos: [
      'Con el teléfono, abra la dirección de la aplicación y entre con su usuario.',
      'Toque su nombre (arriba a la derecha) → «Instalar app», o la opción equivalente de su navegador.',
      'Abra la app desde el icono que se creó en la pantalla de inicio.',
    ],
    ojo: [
      'La opción «Instalar app» solo aparece cuando el navegador puede instalarla.',
      'La app abre en la mensajería; desde ahí se llega al resto de las pantallas por el menú.',
    ],
    palabras: ['pwa', 'celular', 'movil', 'icono', 'instalar'],
  },
  {
    id: 'ayuda-de-la-pantalla',
    tema: 'empezar',
    titulo: 'Ayuda de la pantalla donde estoy',
    resumen: 'El signo de pregunta de la cabecera lleva a las guías de la pantalla actual.',
    para: [],
    pasos: [
      'Pulse el signo de pregunta de la cabecera mientras está en una pantalla.',
      'Aparecen primero las guías de esa pantalla; «Ver todas» muestra el manual completo.',
      'Use la búsqueda para encontrar una palabra: «contraseña», «anular», «encomienda».',
    ],
    ojo: [
      'Las guías que no puede hacer no aparecen: el manual le muestra lo que su cuenta sí puede hacer.',
    ],
    palabras: ['ayuda', 'manual', 'guia', 'buscar en la ayuda'],
  },
  {
    id: 'no-veo-una-opcion',
    tema: 'empezar',
    titulo: 'No veo una opción o no me deja hacer algo',
    resumen: 'El menú solo muestra lo que su cuenta puede hacer.',
    para: [],
    antes: 'Muchas pantallas se veían aunque luego no dejaran operar.',
    pasos: [
      'Si una opción no aparece en el menú, su cuenta no la tiene habilitada: pida a quien administra los accesos que se la habilite.',
      'Si el sistema le dice que no puede (sin permiso, pasó la hora o llegó al límite del día) y ofrece «Pedir autorización», pídala: un supervisor la aprueba y usted repite la operación (vea «Pedir una autorización»).',
      'Si cambian sus accesos mientras trabaja, el menú se actualiza solo; no hace falta salir y volver a entrar.',
    ],
    palabras: ['permiso', 'acceso denegado', 'no aparece', 'menu', 'autorizacion', 'no me deja'],
  },
]
