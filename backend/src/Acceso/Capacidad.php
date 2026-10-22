<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Entity\Enum\Dispensa;

/**
 * Catálogo de capacidades del dominio (ADR-039): lo que el código exige con
 * `isGranted()`. Vive en el código porque solo el código las consulta; los
 * roles (datos) las agrupan y las asignaciones las conceden con un alcance.
 *
 * Las del CRUD genérico (`{recurso}.ver|editar|eliminar`) no están aquí:
 * salen del atributo `#[Recurso]` de cada entidad (`Catalogo\CatalogoRecursos`).
 * Si un recurso usa un código de este enum (`ruta.editar`, `boleto.ver`), vale
 * la definición de aquí.
 */
enum Capacidad: string
{
    case VENTA_VENDER = 'venta.vender';
    case VENTA_CORTESIA = 'venta.cortesia';
    case VENTA_SIN_FACTURA = 'venta.sin_factura';

    case BOLETO_VER = 'boleto.ver';
    case BOLETO_ANULAR = 'boleto.anular';
    case BOLETO_REASIGNAR = 'boleto.reasignar';
    case BOLETO_CANCELAR = 'boleto.cancelar';
    case BOLETO_CHEQUEAR = 'boleto.chequear';

    case SALIDA_VER = 'salida.ver';
    case SALIDA_CREAR = 'salida.crear';
    case SALIDA_EDITAR = 'salida.editar';
    case SALIDA_CANCELAR = 'salida.cancelar';
    case SALIDA_ELIMINAR = 'salida.eliminar';
    case SALIDA_ABORDAR = 'salida.abordar';
    case SALIDA_INICIAR = 'salida.iniciar';
    case SALIDA_FINALIZAR = 'salida.finalizar';

    case RUTA_EDITAR = 'ruta.editar';

    case ENCOMIENDA_VER = 'encomienda.ver';
    case ENCOMIENDA_REGISTRAR = 'encomienda.registrar';
    case ENCOMIENDA_INTERNA = 'encomienda.interna';
    case ENCOMIENDA_EMBARCAR = 'encomienda.embarcar';
    case ENCOMIENDA_ENTREGAR = 'encomienda.entregar';
    case ENCOMIENDA_ANULAR = 'encomienda.anular';
    case ENCOMIENDA_ESPECIALES = 'encomienda.especiales';

    case AGENCIA_DEPOSITAR = 'agencia.depositar';
    case AGENCIA_ACREDITAR = 'agencia.acreditar';

    case REPORTE_VENTAS = 'reporte.ventas';
    case REPORTE_ENCOMIENDAS = 'reporte.encomiendas';

    case PAGINA_ADMINISTRAR = 'pagina.administrar';

    case CONFIG_EDITAR = 'config.editar';

    case IAM_VER = 'iam.ver';
    case IAM_ASIGNAR = 'iam.asignar';
    case IAM_ROLES = 'iam.roles';

    case AUTORIZACION_APROBAR = 'autorizacion.aprobar';

    case BITACORA_VER = 'bitacora.ver';

    case MIGRACION_EJECUTAR = 'migracion.ejecutar';

    public function nombre(): string
    {
        return match ($this) {
            self::VENTA_VENDER => 'Vender boletos (taquilla o agencia)',
            self::VENTA_CORTESIA => 'Emitir cortesías (sin cobro)',
            self::VENTA_SIN_FACTURA => 'Vender sin factura electrónica (contingencia)',
            self::BOLETO_VER => 'Ver boletos y ventas',
            self::BOLETO_ANULAR => 'Anular boletos',
            self::BOLETO_REASIGNAR => 'Reasignar boletos',
            self::BOLETO_CANCELAR => 'Cancelar boletos (no viaja) y revertirlos',
            self::BOLETO_CHEQUEAR => 'Chequear boletos al abordar',
            self::SALIDA_VER => 'Ver salidas, su detalle y manifiestos',
            self::SALIDA_CREAR => 'Programar salidas y administrar esquemas',
            self::SALIDA_EDITAR => 'Editar salidas',
            self::SALIDA_CANCELAR => 'Anular salidas',
            self::SALIDA_ELIMINAR => 'Eliminar salidas',
            self::SALIDA_ABORDAR => 'Poner salidas en abordaje',
            self::SALIDA_INICIAR => 'Iniciar salidas',
            self::SALIDA_FINALIZAR => 'Finalizar salidas',
            self::RUTA_EDITAR => 'Editar rutas y sus paradas',
            self::ENCOMIENDA_VER => 'Ver encomiendas',
            self::ENCOMIENDA_REGISTRAR => 'Registrar encomiendas',
            self::ENCOMIENDA_INTERNA => 'Registrar encomiendas internas (sin cobro)',
            self::ENCOMIENDA_EMBARCAR => 'Embarcar y desembarcar encomiendas',
            self::ENCOMIENDA_ENTREGAR => 'Entregar encomiendas',
            self::ENCOMIENDA_ANULAR => 'Anular encomiendas',
            self::ENCOMIENDA_ESPECIALES => 'Administrar el catálogo de encomiendas especiales',
            self::AGENCIA_DEPOSITAR => 'Registrar depósitos y consultar saldos de agencias',
            self::AGENCIA_ACREDITAR => 'Acreditar o rechazar depósitos de agencias',
            self::REPORTE_VENTAS => 'Reportes de venta y tablero de gestión',
            self::REPORTE_ENCOMIENDAS => 'Reportes de caja de encomiendas',
            self::PAGINA_ADMINISTRAR => 'Compras en línea y configuración de la página web',
            self::CONFIG_EDITAR => 'Configurar la aplicación (entidades, menús, administración, publicación)',
            self::IAM_VER => 'Ver usuarios, roles y asignaciones',
            self::IAM_ASIGNAR => 'Otorgar y revocar asignaciones de roles',
            self::IAM_ROLES => 'Crear y editar roles',
            self::AUTORIZACION_APROBAR => 'Aprobar o rechazar autorizaciones (pases de un solo uso)',
            self::BITACORA_VER => 'Ver lo anulado y las bitácoras en el detalle de venta',
            self::MIGRACION_EJECUTAR => 'Ejecutar la migración desde el sistema legado',
        };
    }

    public function area(): string
    {
        return match (explode('.', $this->value)[0]) {
            'venta' => 'Venta',
            'boleto' => 'Boletos',
            'salida' => 'Salidas',
            'ruta' => 'Rutas y tarifas',
            'encomienda' => 'Encomiendas',
            'agencia' => 'Agencias',
            'reporte' => 'Reportes',
            'pagina' => 'Página web',
            'config' => 'Configuración',
            'iam', 'autorizacion' => 'Accesos',
            'bitacora' => 'Auditoría',
            default => 'Sistema',
        };
    }

    /**
     * Por qué se acota esta capacidad: la empresa del registro y la estación
     * donde ocurre la operación. Sin dimensiones, cualquier asignación vigente
     * del rol la concede.
     *
     * @return list<Dimension>
     */
    public function dimensiones(): array
    {
        return match ($this) {
            self::VENTA_VENDER, self::VENTA_CORTESIA, self::VENTA_SIN_FACTURA,
            self::BOLETO_VER, self::BOLETO_ANULAR, self::BOLETO_REASIGNAR, self::BOLETO_CANCELAR, self::BOLETO_CHEQUEAR,
            self::SALIDA_ABORDAR, self::SALIDA_INICIAR, self::SALIDA_FINALIZAR,
            self::ENCOMIENDA_VER, self::ENCOMIENDA_REGISTRAR, self::ENCOMIENDA_INTERNA, self::ENCOMIENDA_EMBARCAR,
            self::ENCOMIENDA_ENTREGAR, self::ENCOMIENDA_ANULAR,
            self::REPORTE_VENTAS, self::REPORTE_ENCOMIENDAS,
            self::IAM_ASIGNAR, self::AUTORIZACION_APROBAR => [Dimension::EMPRESA, Dimension::ESTACION],
            self::SALIDA_VER, self::SALIDA_CREAR, self::SALIDA_EDITAR, self::SALIDA_CANCELAR, self::SALIDA_ELIMINAR,
            self::BITACORA_VER => [Dimension::EMPRESA],
            default => [],
        };
    }

    /**
     * De qué reglas puede dispensar una autorización al operar con esta
     * capacidad (ADR-040). Vacío: no se autoriza por excepción.
     *
     * @return list<Dispensa>
     */
    public function dispensas(): array
    {
        return match ($this) {
            self::BOLETO_ANULAR, self::BOLETO_REASIGNAR => [Dispensa::PERMISO, Dispensa::LIMITE, Dispensa::HORA],
            self::VENTA_CORTESIA => [Dispensa::PERMISO, Dispensa::LIMITE],
            default => [],
        };
    }

    /** @return list<Limite> los límites que un rol puede fijarle */
    public function limites(): array
    {
        return array_values(array_filter(Limite::cases(), fn(Limite $l) => $l->capacidad() === $this));
    }
}
