/**
 * Usuarios y accesos. Cada guía exige la operación concreta que describe: quien
 * no puede hacerla no la ve, ni su título ni sus pasos. Los nombres de los
 * puestos (roles) se mencionan solo donde la pantalla los muestra.
 */
import type { Guia } from '@/core/ayuda/tipos'
import {
  ACCESOS_ASIGNAR,
  ACCESOS_ROLES,
  ACCESOS_VER,
  MIGRACION,
  USUARIOS_EDITAR,
  USUARIOS_VER,
} from './audiencias'

const USUARIOS = { name: 'entity-list', params: { entity: 'usuario' } }

export const guiasAccesos: Guia[] = [
  {
    id: 'buscar-usuarios',
    tema: 'accesos',
    titulo: 'Buscar usuarios',
    resumen: 'El listado de empleados: quién está activo, dónde trabaja y qué le falta.',
    para: [USUARIOS_VER],
    pasos: [
      'Abra «Usuarios» en el menú.',
      'Busque por nombre, usuario o agencia. El filtro de activos muestra solo los que pueden entrar.',
      'Pulse un usuario para ver su ficha; «Accesos» abre lo que puede hacer y dónde.',
    ],
    ojo: [
      'Un usuario sin accesos vigentes puede entrar, pero no puede operar: aparece en «Sin alcance» de la pantalla de «¿Quién puede?».',
    ],
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['entity-list:usuario'],
    palabras: ['buscar empleado', 'lista de usuarios', 'quien trabaja aqui'],
  },
  {
    id: 'crear-usuario',
    tema: 'accesos',
    titulo: 'Crear un usuario',
    resumen: 'Alta de un empleado nuevo. Después hay que darle accesos para que pueda operar.',
    para: [USUARIOS_EDITAR],
    pasos: [
      'Abra «Usuarios» y pulse «Nuevo» (el +) en la barra.',
      'Complete usuario (con el que entra), nombre, apellido y una contraseña inicial.',
      'Si trabaja para una agencia, elíjala en «Agencia»: venderá con el saldo de esa agencia.',
      'Guarde. Luego dele accesos (vea «Darle accesos a un usuario»).',
    ],
    ojo: [
      'Sin accesos, el usuario entra y usa el chat, pero no puede vender ni operar.',
      'Pídale que cambie su contraseña desde «Mi cuenta» la primera vez que entre.',
    ],
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['entity-list:usuario', 'entity-form:usuario'],
    palabras: ['nuevo usuario', 'alta', 'empleado nuevo', 'registrar usuario'],
  },
  {
    id: 'editar-usuario',
    tema: 'accesos',
    titulo: 'Editar un usuario',
    resumen:
      'Cambiar los datos de una persona: nombre, correo, teléfono, agencia y si está activa.',
    para: [USUARIOS_EDITAR],
    pasos: [
      'En «Usuarios», pulse el usuario o su «Editar».',
      'Cambie los datos que necesite y pulse «Guardar».',
      'Para un usuario de una agencia, cambie la agencia en el mismo formulario.',
    ],
    ojo: [
      'Editar los datos no cambia lo que el usuario puede hacer: eso está en «Accesos».',
      'La contraseña no se cambia aquí: use «Cambiar contraseña» en la fila del usuario.',
      'Solo puede editar a usuarios cuyos accesos usted podría dar. Si no ve el botón, ese usuario está fuera de su alcance.',
    ],
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['entity-form:usuario'],
    palabras: [
      'modificar usuario',
      'cambiar nombre',
      'correo del empleado',
      'telefono',
      'corregir usuario',
    ],
  },
  {
    id: 'poner-contrasena',
    tema: 'accesos',
    titulo: 'Poner otra contraseña a un usuario',
    resumen: 'Cuando alguien olvidó la suya.',
    para: [USUARIOS_EDITAR],
    pasos: [
      'En «Usuarios», en la fila de la persona, pulse «Cambiar contraseña».',
      'Escriba dos veces la nueva y pulse «Guardar».',
      'Désela en persona y pídale que la cambie desde «Mi cuenta».',
    ],
    ojo: ['Sus sesiones abiertas se cierran y el cambio queda en su bitácora.'],
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['entity-list:usuario'],
    palabras: ['olvido contrasena', 'resetear', 'restablecer', 'clave', 'nueva clave'],
  },
  {
    id: 'darle-accesos',
    tema: 'accesos',
    titulo: 'Darle accesos a un usuario',
    resumen: 'Qué puede hacer la persona y dónde (empresas y estaciones), en un solo paso.',
    para: [ACCESOS_ASIGNAR],
    antes:
      'Roles, empresas y estaciones se marcaban por separado y valían juntos para todo. Ahora cada puesto lleva su propio alcance: un vendedor puede vender en una estación y solo consultar en otra.',
    pasos: [
      'En «Usuarios», en la fila de la persona, pulse «Accesos (roles y alcance)».',
      'Pulse «Otorgar rol» y elija el puesto (rol).',
      'Empresas: elija una o varias, o active «Todas».',
      'Estaciones: elija dónde opera, o active «Todas (oficina)» para puestos que no trabajan en taquilla.',
      'Escriba el motivo (cambio de puesto, ingreso…) y pulse «Otorgar».',
      'Revise abajo «Lo que puede hacer»: es lo que tiene en efecto, sumando todos sus puestos.',
    ],
    ejemplo: {
      caso: 'Nueva vendedora en la taquilla de Flores, que vende para dos empresas.',
      pasos: [
        'Puesto: «Vende boletos en las estaciones».',
        'Empresas: las dos para las que vende.',
        'Estaciones: Flores.',
        'Motivo: «Ingreso».',
        'Al entrar, su estación de trabajo queda fija en Flores.',
      ],
    },
    ojo: [
      'Rige al instante: el usuario no necesita volver a entrar.',
      'Para un usuario de agencia no se eligen empresas ni estaciones: valen su agencia y las empresas que esta vende.',
      'Solo puede otorgar puestos cuyos accesos usted tiene en ese mismo alcance, y nunca a sí mismo.',
    ],
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['acceso-usuario', 'entity-list:usuario'],
    palabras: [
      'asignar rol',
      'permisos',
      'dar acceso',
      'empresas del usuario',
      'estaciones del usuario',
      'otorgar',
    ],
  },
  {
    id: 'suplencia',
    tema: 'accesos',
    titulo: 'Cubrir vacaciones o una suplencia',
    resumen: 'Un puesto que vale solo unos días y vence solo.',
    para: [ACCESOS_ASIGNAR],
    antes: 'Había que acordarse de quitar el rol cuando volvía el titular.',
    pasos: [
      'Otorgue el puesto como siempre («Accesos» → «Otorgar rol»).',
      'Llene «Desde» y «Hasta» (el último día cuenta).',
      'En el motivo escriba a quién cubre.',
    ],
    ejemplo: {
      caso: 'Un vendedor de Santa Elena cubre al supervisor del 1 al 15 de noviembre.',
      pasos: [
        'Puesto: «Responsable de la venta de boletos en las estaciones» · Estación: Santa Elena.',
        'Desde 01/11, hasta 15/11.',
        'Motivo: «Vacaciones de J. Pérez».',
        'El 16 la asignación aparece como «No vigente» y deja de valer sola.',
      ],
    },
    abrir: { etiqueta: 'Abrir Usuarios', destino: USUARIOS },
    pantallas: ['acceso-usuario'],
    palabras: ['temporal', 'vacaciones', 'vigencia', 'reemplazo', 'turno'],
  },
  {
    id: 'cambiar-o-quitar-accesos',
    tema: 'accesos',
    titulo: 'Cambiar o quitar un puesto',
    resumen: 'Ampliar o reducir el alcance, o revocar un puesto.',
    para: [ACCESOS_ASIGNAR],
    pasos: [
      'Abra los «Accesos» del usuario.',
      'En la asignación: el lápiz para modificar (empresas, estaciones, fechas) o el escudo tachado para revocar.',
      '«Bitácora» muestra quién otorgó o quitó cada cosa y cuándo.',
    ],
    ojo: ['Al revocar deja de poder hacerlo de inmediato, aunque tenga la pantalla abierta.'],
    pantallas: ['acceso-usuario'],
    palabras: ['revocar', 'quitar permiso', 'modificar asignacion', 'historial'],
  },
  {
    id: 'dar-de-baja',
    tema: 'accesos',
    titulo: 'Dar de baja a un usuario',
    resumen: 'Desactivar, no eliminar: no entra más y se conserva su historial.',
    para: [ACCESOS_ASIGNAR],
    antes:
      'Se bloqueaba o deshabilitaba. Los usuarios que ya no entraban en el sistema anterior llegaron desactivados.',
    pasos: ['Abra los «Accesos» del usuario.', 'Pulse «Desactivar» y confirme.'],
    ojo: [
      'Se cierran sus sesiones abiertas al momento, también en el teléfono.',
      'Conserva sus puestos: «Activar» lo devuelve tal como estaba.',
      'Eliminar un usuario no se puede deshacer. Úselo solo para usuarios creados por error; para una baja, desactive.',
    ],
    pantallas: ['acceso-usuario', 'entity-list:usuario'],
    palabras: ['bloquear', 'deshabilitar', 'despido', 'renuncia', 'desactivar', 'eliminar usuario'],
  },
  {
    id: 'quien-puede',
    tema: 'accesos',
    titulo: '¿Quién puede hacer esto aquí?',
    resumen:
      'Qué usuarios pueden anular, vender o acreditar en una empresa o estación, y por qué puesto.',
    para: [ACCESOS_VER],
    pasos: [
      'Abra «¿Quién puede?» en el menú de accesos.',
      'Elija la operación (por ejemplo «Anular boletos»), y si quiere la empresa y la estación.',
      'Pulse «Buscar». Cada fila dice el usuario, el puesto que se lo da y dónde.',
      'La pestaña «Sin alcance» lista usuarios activos que no pueden operar: revíselos después de la migración.',
    ],
    abrir: { etiqueta: 'Abrir ¿Quién puede?', destino: { name: 'accesos' } },
    pantallas: ['accesos'],
    palabras: ['auditoria', 'quien tiene permiso', 'sin rol', 'sin alcance'],
  },
  {
    id: 'definir-rol',
    tema: 'accesos',
    titulo: 'Crear o cambiar un rol',
    resumen: 'Qué operaciones concede un puesto y sus límites por día.',
    para: [ACCESOS_ROLES],
    antes:
      'Los límites de vouchers y reasignaciones eran de cada usuario. Ahora son del rol y valen para todos los que lo tienen.',
    pasos: [
      'Abra la pantalla «Roles» y elija el rol, o pulse «Nuevo rol».',
      'Marque las operaciones por área. En «Datos» marque Ver, Editar o Eliminar para cada listado.',
      'Si el rol permite cortesías o reasignar, puede poner su límite por día (vacío = sin límite).',
      'Pulse «Guardar rol» o «Crear rol».',
    ],
    ojo: [
      'El número junto a cada rol es cuántos usuarios lo tienen: el cambio les llega a todos al instante.',
      'Los roles no heredan unos de otros: un supervisor que también vende necesita las operaciones de venta en su propio rol.',
      'El rol de sistema lo puede todo y no se edita; otórguelo solo a quien administra la plataforma.',
    ],
    abrir: { etiqueta: 'Abrir Roles', destino: { name: 'acceso-roles' } },
    pantallas: ['acceso-roles'],
    palabras: [
      'perfil',
      'permisos del rol',
      'limite',
      'cortesias por dia',
      'voucher',
      'operaciones del rol',
    ],
  },
  {
    id: 'equivalencias-accesos',
    tema: 'accesos',
    titulo: 'Qué cambió en accesos: del sistema anterior al nuevo',
    resumen: 'Las piezas de antes y dónde están ahora. Léalo primero.',
    para: [ACCESOS_VER, USUARIOS_EDITAR],
    tabla: [
      {
        antes: 'Al usuario se le marcaban roles, empresas y estaciones por separado',
        ahora:
          'Cada rol se otorga con su propio alcance: qué empresas y qué estaciones (una «asignación»)',
      },
      {
        antes: 'Los mismos roles (ROLE_VENDEDOR_BOLETOS, ROLE_SUPERVISOR_BOLETO…)',
        ahora: 'Se conservan con su nombre; lo que concede cada uno se ve en la pantalla Roles',
      },
      {
        antes: 'La estación fija del usuario',
        ahora: 'Su estación de trabajo, que él mismo elige entre las de sus roles',
      },
      {
        antes: 'Usuario habilitado / bloqueado / expirado',
        ahora: 'Activo o Desactivado (un desactivado no entra)',
      },
      {
        antes: 'Vouchers permitidos en el día y cupo de reasignaciones, por usuario',
        ahora: 'Límites del rol: cortesías por día y reasignaciones por día',
      },
      {
        antes: 'ROLE_NO_VENDER_VOUCHER (quitar algo a un usuario)',
        ahora: 'No hay roles que quiten: simplemente no se le otorga un rol con cortesías',
      },
      {
        antes: 'Quitar el rol a mano al terminar una suplencia',
        ahora: 'Fechas «Desde» y «Hasta» en la asignación: vence sola',
      },
    ],
    abrir: { etiqueta: 'Ver roles', destino: { name: 'acceso-roles' } },
    pantallas: ['acceso-roles', 'acceso-usuario', 'accesos', 'entity-list:usuario'],
    palabras: ['migracion', 'legado', 'sistema viejo', 'terminal omnibus'],
  },
  {
    id: 'migracion-del-sistema-anterior',
    tema: 'accesos',
    titulo: 'Pasar los datos del sistema anterior',
    resumen: 'La pantalla de migración: los pasos para traer lo que falta del sistema anterior.',
    para: [MIGRACION],
    pasos: [
      'Abra «Migración» desde el menú.',
      'Ejecute los pasos en el orden que indica la pantalla y espere a que cada uno termine antes del siguiente.',
      'Revise el resultado de cada paso antes de seguir.',
    ],
    ojo: [
      'Antes de cualquier paso que modifique datos, haga un respaldo de la base.',
      'La mayoría de los pasos no se deshacen: consulte con quien administra el sistema si tiene dudas.',
    ],
    abrir: { etiqueta: 'Abrir Migración', destino: { name: 'migracion' } },
    pantallas: ['migracion'],
    palabras: ['importar', 'legado', 'traspaso', 'sistema anterior'],
  },
]
