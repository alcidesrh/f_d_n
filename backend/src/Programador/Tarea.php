<?php

declare(strict_types=1);

namespace App\Programador;

use App\Actividad\Tarea\MantenerBitacora;
use App\Afectacion\Tarea\VigilarAfectaciones;
use App\Chat\Predeterminado\Tarea\EnviarMensajesPredeterminados;
use App\Chat\Predeterminado\Tarea\RevisarAnomalias;
use App\Chat\Tarea\PurgarArchivosChat;
use App\Mantenimiento\Tarea\RevisarMantenimiento;
use App\Reporte\Diario\EnviarReportesDiarios;
use App\Salida\Cierre\CerrarSalidas;
use App\Salida\Horizonte\ExtenderHorizontes;
use App\Seguimiento\Tarea\PurgarLecturasGps;
use App\Sincronizacion\BarrerSalidasLegado;
use App\Sincronizacion\SincronizarLegado;
use App\Tablero\Tarea\RefrescarTablero;
use App\Venta\Facturacion\CertificarPendientes;
use App\Venta\Tarea\PurgarVenta;
use App\Vigilancia\VigilarOperacion;

/**
 * Catálogo de las tareas que corren solas (ADR-044). Conjunto cerrado, como
 * las capacidades: cada una es un trabajo del dominio con su frecuencia, su
 * cola y su mensaje; no hay tareas definidas por el usuario. Lo que sí se
 * configura (quién recibe un reporte, el umbral de un aviso, qué esquema se
 * mantiene solo) vive en los datos del módulo que la usa.
 *
 * - `frecuencia`: cada cuánto (`PeriodicalTrigger`); con `hora`, una vez al
 *   día a esa hora (America/Guatemala).
 * - Todas corren en la cola `tareas` (cortas). Las que reparten trabajo
 *   largo (reportes, horizonte) despachan un mensaje por unidad a la cola
 *   `pesadas`, que tiene su propio worker.
 * - `tolerancia`: sin una corrida buena en ese lapso, el tablero avisa que
 *   el programador está detenido.
 */
enum Tarea: string
{
    case PURGAR_VENTA = 'purgar_venta';
    case CERTIFICAR_PENDIENTES = 'certificar_pendientes';
    case SINCRONIZAR_LEGADO = 'sincronizar_legado';
    case BARRER_SALIDAS_LEGADO = 'barrer_salidas_legado';
    case VIGILAR_OPERACION = 'vigilar_operacion';
    case HORIZONTE_SALIDAS = 'horizonte_salidas';
    case REPORTES_DIARIOS = 'reportes_diarios';
    case PURGAR_ARCHIVOS_CHAT = 'purgar_archivos_chat';
    case MENSAJES_PREDETERMINADOS = 'mensajes_predeterminados';
    case REVISAR_ANOMALIAS = 'revisar_anomalias';
    case REFRESCAR_TABLERO = 'refrescar_tablero';
    case PURGAR_LECTURAS_GPS = 'purgar_lecturas_gps';
    case BITACORA_DIARIA = 'bitacora_diaria';
    case MANTENIMIENTO_FLOTA = 'mantenimiento_flota';
    case VIGILAR_AFECTACIONES = 'vigilar_afectaciones';
    case CERRAR_SALIDAS = 'cerrar_salidas';

    public const COLA_TAREAS = 'tareas';
    public const COLA_PESADAS = 'pesadas';

    public function nombre(): string
    {
        return match ($this) {
            self::PURGAR_VENTA => 'Borrar reservas web vencidas y ventas abandonadas',
            self::CERTIFICAR_PENDIENTES => 'Certificar las facturas pendientes (contingencia)',
            self::SINCRONIZAR_LEGADO => 'Sincronizar con el sistema anterior',
            self::BARRER_SALIDAS_LEGADO => 'Traer las salidas del sistema anterior que faltan aquí',
            self::VIGILAR_OPERACION => 'Vigilar la operación y avisar lo que pide acción',
            self::HORIZONTE_SALIDAS => 'Mantener programadas las salidas de los esquemas automáticos',
            self::REPORTES_DIARIOS => 'Enviar los reportes diarios',
            self::PURGAR_ARCHIVOS_CHAT => 'Borrar archivos del chat que no se enviaron',
            self::MENSAJES_PREDETERMINADOS => 'Enviar los mensajes predeterminados de hora y de salidas iniciadas',
            self::REVISAR_ANOMALIAS => 'Revisar las anomalías del día anterior (reasignaciones y tarifas)',
            self::REFRESCAR_TABLERO => 'Rehacer el tablero de gestión que alguien está mirando',
            self::PURGAR_LECTURAS_GPS => 'Borrar las lecturas GPS de más de 90 días',
            self::BITACORA_DIARIA => 'Bitácora diaria: particiones, resumen del día anterior y retención',
            self::MANTENIMIENTO_FLOTA => 'Sumar al odómetro lo recorrido por GPS y avisar el mantenimiento que vence',
            self::VIGILAR_AFECTACIONES => 'Vigilar las afectaciones abiertas: primer aviso, escalamiento y recordatorios',
            self::CERRAR_SALIDAS => 'Iniciar y finalizar por la hora las salidas que nadie marcó',
        };
    }

    /** Cada cuánto corre (formato de `\DateInterval` relativo). */
    public function frecuencia(): string
    {
        return match ($this) {
            self::PURGAR_VENTA, self::SINCRONIZAR_LEGADO, self::REFRESCAR_TABLERO, self::VIGILAR_AFECTACIONES => '1 minute',
            self::MENSAJES_PREDETERMINADOS, self::CERRAR_SALIDAS => '5 minutes',
            // Cada hora: la hora del resumen es configurable (`ConfiguracionBitacora::horaResumen`).
            self::BITACORA_DIARIA => '1 hour',
            self::CERTIFICAR_PENDIENTES => '10 minutes',
            self::VIGILAR_OPERACION, self::BARRER_SALIDAS_LEGADO => '30 minutes',
            self::HORIZONTE_SALIDAS, self::REPORTES_DIARIOS, self::PURGAR_ARCHIVOS_CHAT, self::REVISAR_ANOMALIAS, self::PURGAR_LECTURAS_GPS, self::MANTENIMIENTO_FLOTA => '1 day',
        };
    }

    /**
     * La hora del día (`HH:MM`) de las diarias; null para las que corren por
     * intervalo. La de los reportes la fija `PROGRAMADOR_HORA_REPORTES`.
     */
    public function hora(string $horaReportes): ?string
    {
        return match ($this) {
            // De madrugada: lejos de la venta y antes de que abran las taquillas.
            self::HORIZONTE_SALIDAS => '01:30',
            self::PURGAR_ARCHIVOS_CHAT => '03:15',
            self::PURGAR_LECTURAS_GPS => '03:45',
            // Antes de la purga de lecturas y de que salgan los primeros buses.
            self::MANTENIMIENTO_FLOTA => '03:30',
            self::REVISAR_ANOMALIAS => '02:30',
            self::REPORTES_DIARIOS => $horaReportes,
            default => null,
        };
    }

    /** Cuándo corre, para la pantalla: «Cada 10 minutos», «Cada día a las 01:30». */
    public function cuando(string $horaReportes): string
    {
        $hora = $this->hora($horaReportes);
        if ($hora !== null) {
            return "Cada día a las {$hora}";
        }
        [$n, $unidad] = explode(' ', $this->frecuencia());

        return $n === '1' ? 'Cada minuto' : "Cada {$n} minutos";
    }

    /** Sin una corrida buena en este lapso, el programador se da por detenido. */
    public function tolerancia(): string
    {
        return match ($this) {
            self::PURGAR_VENTA, self::SINCRONIZAR_LEGADO, self::REFRESCAR_TABLERO, self::VIGILAR_AFECTACIONES => '15 minutes',
            self::CERTIFICAR_PENDIENTES, self::MENSAJES_PREDETERMINADOS, self::CERRAR_SALIDAS => '1 hour',
            self::VIGILAR_OPERACION, self::BARRER_SALIDAS_LEGADO => '2 hours',
            self::BITACORA_DIARIA => '3 hours',
            self::HORIZONTE_SALIDAS, self::REPORTES_DIARIOS, self::PURGAR_ARCHIVOS_CHAT, self::REVISAR_ANOMALIAS, self::PURGAR_LECTURAS_GPS, self::MANTENIMIENTO_FLOTA => '26 hours',
        };
    }

    public function mensaje(): MensajeDeTarea
    {
        return match ($this) {
            self::PURGAR_VENTA => new PurgarVenta(),
            self::CERTIFICAR_PENDIENTES => new CertificarPendientes(),
            self::SINCRONIZAR_LEGADO => new SincronizarLegado(),
            self::BARRER_SALIDAS_LEGADO => new BarrerSalidasLegado(),
            self::VIGILAR_OPERACION => new VigilarOperacion(),
            self::HORIZONTE_SALIDAS => new ExtenderHorizontes(),
            self::REPORTES_DIARIOS => new EnviarReportesDiarios(),
            self::PURGAR_ARCHIVOS_CHAT => new PurgarArchivosChat(),
            self::MENSAJES_PREDETERMINADOS => new EnviarMensajesPredeterminados(),
            self::REVISAR_ANOMALIAS => new RevisarAnomalias(),
            self::REFRESCAR_TABLERO => new RefrescarTablero(),
            self::PURGAR_LECTURAS_GPS => new PurgarLecturasGps(),
            self::BITACORA_DIARIA => new MantenerBitacora(),
            self::MANTENIMIENTO_FLOTA => new RevisarMantenimiento(),
            self::VIGILAR_AFECTACIONES => new VigilarAfectaciones(),
            self::CERRAR_SALIDAS => new CerrarSalidas(),
        };
    }
}
