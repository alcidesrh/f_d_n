<?php

declare(strict_types=1);

namespace App\Acceso\Legado;

/**
 * Qué capacidades concede cada rol del legado (`custom_rol`) en el modelo
 * nuevo (ADR-039), y si su alcance es por estación (lo ejerce en las
 * estaciones del usuario) o sobre todas (oficinas). La empresa siempre sale
 * de las del usuario en el legado (`custom_user_empresa`).
 *
 * Es una decisión de negocio: revisarla con quien opera cada puesto.
 * `ROLE_SUPER_ADMIN` es el rol de sistema; `ROLE_ADMIN`, todo menos la
 * migración; aprueban autorizaciones (ADR-040) los que lo hacían en el legado; `ROLE_USER` (el que el
 * legado daba a todos) no se migra: en el modelo nuevo no hay rol base; `ROLE_NO_VENDER_VOUCHER` no
 * concede nada (era una negación: el modelo nuevo no las tiene).
 *
 * El legado apilaba un rol por privilegio; aquí un rol es un puesto (ADR-052):
 * los que son el mismo puesto se fusionan (`FUSIONES`) y los que solo repetían
 * parte de otro se quitan a quien ya tiene ese otro (`ABSORBIDOS`).
 */
final class EquivalenciasLegado
{
    public const SISTEMA = 'ROLE_SUPER_ADMIN';
    public const ADMIN = 'ROLE_ADMIN';
    /** El que el legado daba a todos: no se migra (ADR-049). */
    public const BASE = 'ROLE_USER';
    /** Lo que `ROLE_ADMIN` no tiene del catálogo. */
    public const ADMIN_EXCLUYE = ['migracion.ejecutar'];
    /** Era una negación: no concede nada y no se migra. */
    public const SIN_EFECTO = 'ROLE_NO_VENDER_VOUCHER';

    /** @var array<string, string> rol del legado => el puesto en que quedó (mismas capacidades) */
    public const FUSIONES = [
        'ROLE_CHECK_BOLETO_WEB' => 'ROLE_CHECK_BOLETO',
        'ROLE_CHECK_BOLETO_MOVIL' => 'ROLE_CHECK_BOLETO',
        'ROLE_ENTREGA_MULTIPLE_ENCOMIENDA' => 'ROLE_RECEPTOR_ENCOMIENDAS',
    ];

    /** Sin puesto propio: lo que dan ya viene en otros. El rol sigue solo si a alguien no se lo cubre otro. */
    public const ABSORBIDOS = ['ROLE_ADMIN_REIMPRIMIR_FACTURA', 'ROLE_EMISOR_CORTESIA', 'ROLE_SUPERVISOR_BOLETO_VOUCHER'];

    /** @var array<string, array{0: bool, 1: list<string>}> rol => [por estación, capacidades] */
    public const ROLES = [
        'ROLE_ADMIN_EMPRESA' => [false, [
            'salida.ver', 'salida.crear', 'salida.editar', 'salida.cancelar', 'salida.eliminar',
            'salida.abordar', 'salida.iniciar', 'salida.finalizar',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar',
            'venta.vender', 'venta.cortesia', 'venta.sin_factura',
            'reporte.ventas', 'reporte.encomiendas', 'encomienda.ver',
            'bus.ver', 'bus.editar', 'piloto.ver', 'piloto.editar', 'tarifa.ver', 'tarifa.editar',
            'establecimiento.ver', 'empresa.editar', 'iam.ver', 'iam.asignar', 'usuario.ver', 'usuario.editar', 'bitacora.ver', 'autorizacion.aprobar',
        ]],
        'ROLE_ADMIN_ESTACION' => [true, [
            'venta.vender', 'venta.cortesia', 'venta.sin_factura',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear',
            'salida.ver', 'salida.abordar', 'salida.iniciar', 'salida.finalizar',
            'encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.anular',
            'reporte.ventas', 'reporte.encomiendas', 'iam.ver', 'usuario.ver', 'autorizacion.aprobar',
        ]],
        'ROLE_ADMIN_BUSES' => [false, ['bus.ver', 'bus.editar', 'bus.eliminar', 'bus_clase.editar', 'bus_marca.editar', 'salida.ver']],
        'ROLE_ADMIN_PILOTOS' => [false, ['piloto.ver', 'piloto.editar', 'piloto.eliminar', 'bus.ver']],
        'ROLE_ADMIN_REIMPRIMIR_FACTURA' => [true, ['boleto.ver']],
        'ROLE_ADMIN_TARIFAS' => [false, [
            'tarifa.ver', 'tarifa.editar', 'tarifa.eliminar',
            'tarifa_encomienda_distancia.editar', 'tarifa_encomienda_peso.editar', 'bus.ver', 'salida.ver',
        ]],
        'ROLE_ADMIN_TIEMPOS' => [false, ['ruta.editar', 'salida.ver']],
        'ROLE_ADMINISTRATIVOS' => [false, ['agencia.depositar', 'agencia.acreditar', 'reporte.ventas', 'reporte.encomiendas', 'boleto.ver', 'salida.ver', 'encomienda.ver']],
        'ROLE_AGENCIA' => [false, ['venta.vender', 'boleto.ver', 'agencia.depositar']],
        'ROLE_AGENCIA_SALIDA' => [false, ['salida.abordar', 'salida.iniciar', 'boleto.chequear', 'salida.ver']],
        'ROLE_ALQUILER' => [false, ['bus.ver', 'salida.ver']],
        'ROLE_ASIGNADOR_BUSES_PILOTOS' => [false, ['salida.ver', 'salida.crear', 'salida.editar', 'bus.ver', 'bus.editar', 'piloto.ver']],
        'ROLE_AUTORIZADOR' => [true, ['venta.cortesia', 'boleto.ver', 'autorizacion.aprobar']],
        'ROLE_CHECK_BOLETO' => [true, ['boleto.chequear', 'salida.ver']],
        'ROLE_EMISOR_CORTESIA' => [true, ['venta.vender', 'venta.cortesia']],
        'ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.especiales']],
        'ROLE_INSPECTOR_BOLETO' => [false, ['boleto.ver', 'salida.ver']],
        'ROLE_INSPECTOR_ENCOMIENDA' => [false, ['encomienda.ver']],
        'ROLE_MANIFIESTO_ESPECIAL' => [false, ['salida.ver']],
        'ROLE_PROPIETARIO' => [false, ['reporte.ventas', 'reporte.encomiendas', 'agencia.depositar', 'agencia.acreditar', 'salida.ver', 'boleto.ver', 'encomienda.ver', 'bitacora.ver', 'autorizacion.aprobar']],
        'ROLE_REASIGNACION' => [true, ['boleto.ver', 'boleto.reasignar']],
        'ROLE_RECEPTOR_ENCOMIENDAS' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar']],
        'ROLE_REVISOR' => [false, ['boleto.ver', 'encomienda.ver', 'salida.ver']],
        'ROLE_SUPERVISOR_AGENCIA' => [false, ['agencia.depositar', 'agencia.acreditar']],
        'ROLE_SUPERVISOR_BOLETO' => [true, [
            'venta.vender', 'venta.cortesia', 'venta.sin_factura',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear',
            'salida.ver', 'reporte.ventas', 'cliente.ver', 'cliente.editar',
        ]],
        'ROLE_SUPERVISOR_BOLETO_VOUCHER' => [true, ['boleto.ver', 'reporte.ventas']],
        'ROLE_SUPERVISOR_ENCOMIENDA' => [true, [
            'encomienda.ver', 'encomienda.registrar', 'encomienda.interna', 'encomienda.embarcar',
            'encomienda.entregar', 'encomienda.anular', 'encomienda.especiales', 'reporte.encomiendas',
        ]],
        'ROLE_SUPERVISOR_PORTAL' => [false, ['pagina.administrar', 'boleto.ver']],
        'ROLE_VENDEDOR_BOLETOS' => [true, ['venta.vender', 'boleto.ver']],
    ];

    /** El rol del modelo nuevo para uno del legado; null si no se migra. */
    public static function puesto(string $rol): ?string
    {
        return $rol === self::BASE || $rol === self::SIN_EFECTO ? null : (self::FUSIONES[$rol] ?? $rol);
    }

    /**
     * Capacidades del rol (con el catálogo para `ROLE_ADMIN`); null si es el
     * de sistema o el base, que no llevan lista.
     *
     * @param list<string> $catalogo
     *
     * @return list<string>|null
     */
    public static function capacidades(string $rol, array $catalogo): ?array
    {
        return match ($rol) {
            self::SISTEMA, self::BASE => null,
            self::ADMIN => array_values(array_diff($catalogo, self::ADMIN_EXCLUYE)),
            default => self::ROLES[$rol][1] ?? [],
        };
    }

    /** Se ejerce en las estaciones del usuario (no en todas). */
    public static function porEstacion(string $rol): bool
    {
        return self::ROLES[$rol][0] ?? false;
    }

    /** Sobre todas las empresas y estaciones, sin mirar las del usuario. */
    public static function total(string $rol): bool
    {
        return $rol === self::SISTEMA || $rol === self::ADMIN;
    }
}
