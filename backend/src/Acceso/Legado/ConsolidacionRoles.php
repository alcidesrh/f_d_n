<?php

declare(strict_types=1);

namespace App\Acceso\Legado;

/**
 * De los roles del legado a los puestos del modelo nuevo (ADR-052). Puro.
 *
 * El legado apilaba roles chicos (vender, chequear, reimprimir, reasignar…)
 * y quitaba con una negación (`ROLE_NO_VENDER_VOUCHER`). El modelo nuevo no
 * tiene negaciones y un rol es un puesto (ADR-039). Aquí se calcula lo que el
 * usuario podía hacer en el legado (`EquivalenciasLegado::ROLES` menos lo que
 * le negaban) y se cubre con la menor cantidad de puestos (`PUESTOS`) del
 * mismo tipo de alcance, sin darle nada que no tuviera. Si algo no se puede
 * cubrir exacto, se cubre con el puesto más chico que lo contiene, y queda en
 * `ampliadas` para revisarlo.
 *
 * La cortesía es un puesto aparte (`ROLE_EMISOR_CORTESIA`, de estación o de
 * oficina): así «no vende vouchers» es simplemente no otorgarlo, como pide
 * ADR-039.
 */
final class ConsolidacionRoles
{
    public const ENCARGADO_TAQUILLA = 'ROLE_ENCARGADO_TAQUILLA';
    public const EMISOR_CORTESIA = 'ROLE_EMISOR_CORTESIA';
    public const CHEQUEO_BOLETO = 'ROLE_CHEQUEO_BOLETO';

    /**
     * Los puestos que crea la migración: nombre => [por estación, capacidades, descripción].
     *
     * @var array<string, array{0: bool, 1: list<string>, 2: string}>
     */
    public const PUESTOS = [
        // Estación de trabajo (se ejercen en las estaciones del usuario).
        self::ENCARGADO_TAQUILLA => [true, [
            'venta.vender', 'venta.sin_factura',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear',
            'salida.ver', 'reporte.ventas', 'cliente.ver', 'cliente.editar',
            'encomienda.ver', 'encomienda.registrar', 'encomienda.interna', 'encomienda.embarcar',
            'encomienda.entregar', 'encomienda.anular', 'encomienda.especiales', 'reporte.encomiendas',
        ], 'Responsable de boletos y encomiendas en la estación'],
        'ROLE_SUPERVISOR_BOLETO' => [true, [
            'venta.vender', 'venta.sin_factura',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear',
            'salida.ver', 'reporte.ventas', 'cliente.ver', 'cliente.editar',
        ], 'Responsable de la venta de boletos en las estaciones'],
        'ROLE_SUPERVISOR_ENCOMIENDA' => [true, [
            'encomienda.ver', 'encomienda.registrar', 'encomienda.interna', 'encomienda.embarcar',
            'encomienda.entregar', 'encomienda.anular', 'encomienda.especiales', 'reporte.encomiendas',
        ], 'Responsable de las encomiendas en las estaciones'],
        'ROLE_ADMIN_ESTACION' => [true, [
            'venta.vender', 'venta.sin_factura',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear',
            'salida.ver', 'salida.abordar', 'salida.iniciar', 'salida.finalizar',
            'encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.anular',
            'reporte.ventas', 'reporte.encomiendas', 'iam.ver', 'usuario.ver', 'autorizacion.aprobar',
        ], 'Administra el sistema en las estaciones'],
        'ROLE_VENDEDOR_BOLETOS' => [true, ['venta.vender', 'boleto.ver'], 'Vende boletos en las estaciones'],
        self::CHEQUEO_BOLETO => [true, ['boleto.chequear', 'salida.ver'], 'Chequea boletos al abordar (web y móvil)'],
        'ROLE_REASIGNACION' => [true, ['boleto.ver', 'boleto.reasignar'], 'Reasigna boletos'],
        'ROLE_ADMIN_REIMPRIMIR_FACTURA' => [true, ['boleto.ver'], 'Consulta boletos y reimprime sus facturas'],
        'ROLE_SUPERVISOR_BOLETO_VOUCHER' => [true, ['boleto.ver', 'reporte.ventas'], 'Consulta boletos y reportes de venta de la estación'],
        self::EMISOR_CORTESIA => [true, ['venta.vender', 'venta.cortesia'], 'Emite cortesías'],
        'ROLE_AUTORIZADOR' => [true, ['boleto.ver', 'autorizacion.aprobar'], 'Aprueba autorizaciones'],
        'ROLE_RECEPTOR_ENCOMIENDAS' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar'], 'Recibe y entrega encomiendas en las estaciones'],
        'ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.especiales'], 'Recibe y entrega encomiendas, también las especiales'],
        // Oficina (sobre las empresas del usuario, todas sus estaciones).
        'ROLE_ADMIN_EMPRESA' => [false, [
            'salida.ver', 'salida.crear', 'salida.editar', 'salida.cancelar', 'salida.eliminar',
            'salida.abordar', 'salida.iniciar', 'salida.finalizar',
            'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar',
            'venta.vender', 'venta.sin_factura',
            'reporte.ventas', 'reporte.encomiendas', 'encomienda.ver',
            'bus.ver', 'bus.editar', 'piloto.ver', 'piloto.editar', 'tarifa.ver', 'tarifa.editar',
            'establecimiento.ver', 'empresa.editar', 'iam.ver', 'iam.asignar', 'usuario.ver', 'usuario.editar', 'bitacora.ver', 'autorizacion.aprobar',
        ], 'Administra el sistema por empresa'],
        'ROLE_ADMINISTRATIVOS' => [false, EquivalenciasLegado::ROLES['ROLE_ADMINISTRATIVOS'][1], 'Personal de oficina de administración'],
        'ROLE_PROPIETARIO' => [false, EquivalenciasLegado::ROLES['ROLE_PROPIETARIO'][1], 'Dueños de las empresas'],
        'ROLE_ADMIN_BUSES' => [false, EquivalenciasLegado::ROLES['ROLE_ADMIN_BUSES'][1], 'Administra los buses'],
        'ROLE_ADMIN_PILOTOS' => [false, EquivalenciasLegado::ROLES['ROLE_ADMIN_PILOTOS'][1], 'Administra los pilotos'],
        'ROLE_ADMIN_TARIFAS' => [false, EquivalenciasLegado::ROLES['ROLE_ADMIN_TARIFAS'][1], 'Administra las tarifas'],
        'ROLE_ADMIN_TIEMPOS' => [false, EquivalenciasLegado::ROLES['ROLE_ADMIN_TIEMPOS'][1], 'Administra los tiempos de las rutas'],
        'ROLE_ASIGNADOR_BUSES_PILOTOS' => [false, EquivalenciasLegado::ROLES['ROLE_ASIGNADOR_BUSES_PILOTOS'][1], 'Planifica buses y pilotos de las salidas'],
        'ROLE_ALQUILER' => [false, EquivalenciasLegado::ROLES['ROLE_ALQUILER'][1], 'Alquiler de buses'],
        'ROLE_AGENCIA' => [false, EquivalenciasLegado::ROLES['ROLE_AGENCIA'][1], 'Usuarios de agencias'],
        'ROLE_AGENCIA_SALIDA' => [false, EquivalenciasLegado::ROLES['ROLE_AGENCIA_SALIDA'][1], 'Usuarios de agencias que inician salidas'],
        'ROLE_REVISOR' => [false, ['boleto.ver', 'encomienda.ver', 'salida.ver'], 'Revisión de boletos, encomiendas y manifiestos (solo lectura)'],
        'ROLE_SUPERVISOR_PORTAL' => [false, EquivalenciasLegado::ROLES['ROLE_SUPERVISOR_PORTAL'][1], 'Supervisa la página web'],
    ];

    /** Se asignan en los dos tipos de alcance (la cortesía de oficina rige en todas las estaciones de sus empresas). */
    public const EN_AMBOS = [self::EMISOR_CORTESIA];

    /**
     * Roles del legado que ya no son puestos y en cuál quedan (para las
     * audiencias de menús y administración, y para quien los otorgaba a mano).
     * Lo que un usuario podía hacer con ellos lo cubren los puestos que le
     * tocan; el reemplazo solo hace falta donde el rol se nombra.
     *
     * @var array<string, string|null>
     */
    public const RETIRADOS = [
        'ROLE_CHECK_BOLETO_WEB' => self::CHEQUEO_BOLETO,
        'ROLE_CHECK_BOLETO_MOVIL' => self::CHEQUEO_BOLETO,
        'ROLE_ENTREGA_MULTIPLE_ENCOMIENDA' => 'ROLE_RECEPTOR_ENCOMIENDAS',
        'ROLE_INSPECTOR_BOLETO' => 'ROLE_REVISOR',
        'ROLE_INSPECTOR_ENCOMIENDA' => 'ROLE_REVISOR',
        'ROLE_MANIFIESTO_ESPECIAL' => 'ROLE_REVISOR',
        'ROLE_SUPERVISOR_AGENCIA' => 'ROLE_ADMINISTRATIVOS',
        'ROLE_NO_VENDER_VOUCHER' => null,
    ];

    /**
     * Lo que quitaba cada negación del legado.
     *
     * @var array<string, list<string>>
     */
    public const NIEGA = [
        'ROLE_NO_VENDER_VOUCHER' => ['venta.cortesia'],
    ];

    /** @return list<string>|null las capacidades del puesto (null si no es uno con lista) */
    public static function capacidades(string $puesto): ?array
    {
        return self::PUESTOS[$puesto][1] ?? null;
    }

    public static function porEstacion(string $puesto): bool
    {
        return self::PUESTOS[$puesto][0] ?? false;
    }

    /**
     * Lo que podía hacer el usuario en el legado con estos roles, de un tipo
     * de alcance (`$porEstacion`: true = puestos de estación, false = de
     * oficina, null = todos juntos), menos lo que le negaban `$todos`.
     *
     * @param list<string> $roles los de este alcance
     * @param list<string> $todos todos los del usuario (las negaciones rigen para todo)
     *
     * @return list<string>
     */
    public static function capacidadesLegado(array $roles, array $todos, ?bool $porEstacion = null): array
    {
        $caps = [];
        foreach ($roles as $r) {
            if (!isset(EquivalenciasLegado::ROLES[$r])) {
                continue;
            }
            [$estacion, $lista] = EquivalenciasLegado::ROLES[$r];
            if ($porEstacion !== null && $estacion !== $porEstacion) {
                continue;
            }
            foreach ($lista as $c) {
                $caps[$c] = true;
            }
        }
        return self::sinNegadas(array_keys($caps), $todos);
    }

    /**
     * @param list<string> $capacidades
     * @param list<string> $roles
     *
     * @return list<string> sin lo que niegan esos roles, ordenadas y sin repetir
     */
    public static function sinNegadas(array $capacidades, array $roles): array
    {
        $negadas = [];
        foreach ($roles as $r) {
            array_push($negadas, ...(self::NIEGA[$r] ?? []));
        }
        $caps = array_values(array_unique(array_diff($capacidades, $negadas)));
        sort($caps);

        return $caps;
    }

    /**
     * Los puestos que cubren lo que el usuario tenía con esos roles del
     * legado. Con un rol total (sistema o administración) basta ese.
     *
     * @param list<string> $roles      los del alcance que se consolida
     * @param list<string> $todos      todos los del usuario (para las negaciones); por defecto, $roles
     * @param bool|null    $porEstacion tipo de alcance de los puestos (null: cualquiera, p. ej. agencias)
     * @param list<string> $yaTiene    lo que ya le da otra asignación que cubre este alcance (la de oficina sobre las mismas empresas)
     * @param array<string, list<string>>|null $capacidadesDe lo que da cada rol hoy (de la base); por defecto, lo que daba en el legado
     *
     * @return array{puestos: list<string>, ampliadas: list<string>, sinCubrir: list<string>}
     */
    public static function consolidar(array $roles, ?array $todos = null, ?bool $porEstacion = null, array $yaTiene = [], ?array $capacidadesDe = null): array
    {
        $todos ??= $roles;
        if (in_array(EquivalenciasLegado::SISTEMA, $todos, true)) {
            return ['puestos' => in_array(EquivalenciasLegado::SISTEMA, $roles, true) ? [EquivalenciasLegado::SISTEMA] : [], 'ampliadas' => [], 'sinCubrir' => []];
        }
        if (in_array(EquivalenciasLegado::ADMIN, $todos, true)) {
            return ['puestos' => in_array(EquivalenciasLegado::ADMIN, $roles, true) ? [EquivalenciasLegado::ADMIN] : [], 'ampliadas' => [], 'sinCubrir' => []];
        }

        $capacidades = $capacidadesDe === null
            ? self::capacidadesLegado($roles, $todos, $porEstacion)
            : self::sinNegadas(array_merge([], ...array_map(static fn(string $r) => $capacidadesDe[$r] ?? [], $roles)), $todos);

        // La cortesía es una venta (RegistroVenta pide `venta.vender` antes): sin vender no sirve.
        if (!in_array('venta.vender', [...$capacidades, ...$yaTiene], true)) {
            $capacidades = array_values(array_diff($capacidades, ['venta.cortesia']));
        }

        return self::cubrir($capacidades, $porEstacion, $yaTiene);
    }

    /**
     * La menor cantidad de puestos que dan `$capacidades` (voraz, de mayor a
     * menor), sin dar nada fuera de ellas ni de `$libres` (lo que el usuario
     * ya tiene por otra asignación más amplia). Lo que no se pueda cubrir así
     * se cubre con el puesto que solo agregue lectura (`.ver`, reportes),
     * cubra más y sea más chico (`ampliadas`); lo que tampoco, queda en
     * `sinCubrir` para otorgarlo a mano: nunca se amplía con algo que escribe.
     *
     * @param list<string> $capacidades
     * @param list<string> $libres
     *
     * @return array{puestos: list<string>, ampliadas: list<string>, sinCubrir: list<string>}
     */
    public static function cubrir(array $capacidades, ?bool $porEstacion = null, array $libres = []): array
    {
        $candidatos = [];
        foreach (self::PUESTOS as $nombre => [$estacion, $caps]) {
            if ($porEstacion === null || $estacion === $porEstacion || in_array($nombre, self::EN_AMBOS, true)) {
                $candidatos[$nombre] = $caps;
            }
        }
        uksort($candidatos, static fn(string $a, string $b) => [count($candidatos[$b]), $a] <=> [count($candidatos[$a]), $b]);

        $permitidas = array_fill_keys([...$capacidades, ...$libres], true);
        $falta = array_fill_keys(array_diff($capacidades, $libres), true);
        $elegidos = [];
        foreach ($candidatos as $nombre => $caps) {
            if ($falta === []) {
                break;
            }
            if (array_diff_key(array_fill_keys($caps, true), $permitidas) === [] && array_intersect_key($falta, array_fill_keys($caps, true)) !== []) {
                $elegidos[] = $nombre;
                $falta = array_diff_key($falta, array_fill_keys($caps, true));
            }
        }

        $ampliadas = [];
        while ($falta !== []) {
            $mejor = null;
            $puntaje = null;
            foreach ($candidatos as $nombre => $caps) {
                $cubre = count(array_intersect_key($falta, array_fill_keys($caps, true)));
                $agrega = array_keys(array_diff_key(array_fill_keys($caps, true), $permitidas));
                if ($cubre === 0 || array_filter($agrega, static fn(string $c) => !self::esLectura($c)) !== []) {
                    continue;
                }
                $p = [-$cubre, count($agrega), count($caps), $nombre];
                if ($puntaje === null || $p < $puntaje) {
                    [$mejor, $puntaje, $suma] = [$nombre, $p, $agrega];
                }
            }
            if ($mejor === null) {
                break;
            }
            $elegidos[] = $mejor;
            array_push($ampliadas, ...$suma);
            $permitidas += array_fill_keys($suma, true);
            $falta = array_diff_key($falta, array_fill_keys($candidatos[$mejor], true));
        }

        // Fuera los que no aportan nada que no den los demás.
        $necesarias = array_diff($capacidades, $libres);
        usort($elegidos, static fn(string $a, string $b) => [count($candidatos[$a]), $a] <=> [count($candidatos[$b]), $b]);
        foreach ($elegidos as $i => $nombre) {
            $resto = [];
            foreach ($elegidos as $j => $otro) {
                if ($j !== $i && $otro !== null) {
                    $resto += array_fill_keys($candidatos[$otro], true);
                }
            }
            if (array_diff(array_intersect($candidatos[$nombre], $necesarias), array_keys($resto)) === []) {
                $elegidos[$i] = null;
            }
        }
        $elegidos = array_values(array_filter($elegidos));
        sort($elegidos);
        $ampliadas = array_values(array_unique($ampliadas));
        sort($ampliadas);
        $sinCubrir = array_keys($falta);
        sort($sinCubrir);

        return ['puestos' => $elegidos, 'ampliadas' => $ampliadas, 'sinCubrir' => $sinCubrir];
    }

    /** Solo deja ver: lo único con que se puede ampliar al consolidar. */
    public static function esLectura(string $capacidad): bool
    {
        return str_ends_with($capacidad, '.ver') || str_starts_with($capacidad, 'reporte.');
    }
}
