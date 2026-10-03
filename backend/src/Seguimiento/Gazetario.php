<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Coordenadas de respaldo para estaciones del legado sin GPS (solo 15 de 225
 * lo tienen). Primero se busca el lugar por nombre y, si no está, el centro del
 * departamento. Son aproximaciones de poblado, no de la terminal: basta para
 * dibujar el trayecto hasta que se registren las coordenadas reales.
 */
final class Gazetario
{
    /** Clave (sin acentos, minúsculas) contenida en el primer segmento del nombre → [lat, lng]. Las específicas van antes. */
    private const LUGARES = [
        'centra norte' => [14.6469, -90.4510],
        'aguilar batres' => [14.6092, -90.5401],
        'taller guatemala' => [14.6349, -90.5069],
        'guate magica' => [14.5333, -91.6833],
        'cuatro caminos' => [14.9167, -91.4600],
        'los encuentros' => [14.7583, -91.2650],
        'santa elena' => [16.9208, -89.8936],
        'flores' => [16.9300, -89.8920],
        'sayaxche' => [16.5215, -90.1895],
        'melchor de mencos' => [17.0581, -89.1526],
        'cruce de morales' => [15.5010, -88.8380],
        'morales' => [15.4736, -88.8285],
        'los amates' => [15.2556, -89.0944],
        'quirigua' => [15.2681, -89.0397],
        'san luis' => [16.2000, -89.4333],
        'poptun' => [16.3300, -89.4167],
        'rio dulce' => [15.6591, -89.0030],
        'rio hondo' => [15.0333, -89.5833],
        'chal' => [16.6544, -89.7183],
        'el chal' => [16.6544, -89.7183],
        'san francisco' => [16.7850, -89.9217],
        'la libertad' => [16.7833, -90.1167],
        'el naranjo' => [17.2000, -90.8000],
        'bethel' => [16.9333, -90.9500],
        'fray bartolome' => [15.8553, -89.8367],
        'raxhuja' => [15.9000, -89.8350],
        'chahal' => [15.7600, -89.8000],
        'dolores' => [16.5167, -89.4167],
        'el remate' => [16.9319, -89.6347],
        'tikal' => [17.2220, -89.6237],
        'jutiapa' => [14.2833, -89.8917],
        'asuncion mita' => [14.3333, -89.7083],
        'quetzaltenango' => [14.8333, -91.5167],
        'colomba' => [14.7000, -91.7167],
        'coatepeque' => [14.7000, -91.8667],
        'retalhuleu' => [14.5333, -91.6833],
        'metro plaza 4 caminos' => [14.5333, -91.6833],
        'mazatenango' => [14.5333, -91.5000],
        'tecun uman' => [14.6833, -92.1500],
        'malacatan' => [14.9100, -92.0500],
        'catarina' => [14.8630, -92.0830],
        'pajapita' => [14.7211, -92.0394],
        'talisman' => [14.9500, -92.1567],
        'nuevo progreso' => [14.8000, -91.9500],
        'san rafael pie de la cuesta' => [14.9500, -91.9600],
        'tajumulco' => [15.0667, -91.9167],
        'san marcos' => [14.9656, -91.7947],
        'guastatoya' => [14.8540, -90.0680],
        'el rancho' => [14.9333, -90.0000],
        'sanarate' => [14.7833, -90.2000],
        'chimaltenango' => [14.6611, -90.8197],
        'antigua guatemala' => [14.5586, -90.7295],
        'escuintla' => [14.3000, -90.7833],
        'puerto san jose' => [13.9189, -90.8286],
        'puerto iztapa' => [13.9333, -90.7000],
        'monterrico' => [13.8833, -90.4833],
        'gualan' => [15.1264, -89.3633],
        'teculutan' => [14.9917, -89.7125],
        'zacapa' => [14.9722, -89.5303],
        'chiquimula' => [14.8000, -89.5500],
        'ipala' => [14.5500, -89.6167],
        'esquipulas' => [14.5667, -89.3500],
        'el estor' => [15.5333, -89.3333],
        'cadenas' => [15.5833, -89.0500],
        'entrerios' => [15.7000, -88.7500],
        'entre rios' => [15.7000, -88.7500],
        'mariscos' => [15.8333, -89.0000],
        'pto barrios' => [15.7278, -88.5944],
        'puerto barrios' => [15.7278, -88.5944],
        'san salvador' => [13.6929, -89.2182],
        'san pedro sula' => [15.5000, -88.0333],
        'puerto cortez' => [15.8500, -87.9500],
        'tegucigalpa' => [14.0723, -87.1921],
        'belmopan' => [17.2510, -88.7590],
        'san ignacio' => [17.1560, -89.0710],
        'belice' => [17.4941, -88.1847],
        'chetumal' => [18.4946, -88.2955],
        'guatemala' => [14.6349, -90.5069],
    ];

    private const DEPARTAMENTOS = [
        'guatemala' => [14.6349, -90.5069],
        'peten' => [16.9208, -89.8936],
        'izabal' => [15.6000, -88.9000],
        'zacapa' => [14.9722, -89.5303],
        'alta verapaz' => [15.4700, -90.3700],
        'baja verapaz' => [15.1000, -90.3000],
        'jutiapa' => [14.2833, -89.8917],
        'quetzaltenango' => [14.8333, -91.5167],
        'el progreso' => [14.8540, -90.0680],
        'chimaltenango' => [14.6611, -90.8197],
        'solola' => [14.7700, -91.1800],
        'suchitepequez' => [14.5333, -91.5000],
        'san marcos' => [14.9656, -91.7947],
        'chiquimula' => [14.8000, -89.5500],
        'retalhuleu' => [14.5333, -91.6833],
        'escuintla' => [14.3000, -90.7833],
        'santa rosa' => [14.2800, -90.3000],
        'sacatepequez' => [14.5586, -90.7295],
        'huehuetenango' => [15.3200, -91.4700],
        'quiche' => [15.0300, -91.1500],
        'jalapa' => [14.6300, -89.9900],
        'totonicapan' => [14.9100, -91.3600],
    ];

    /** @return array{0: float, 1: float}|null */
    public static function porNombre(string $nombre): ?array
    {
        $primero = self::normalizar(preg_split('/[,;|]/', $nombre)[0] ?? $nombre);
        foreach (self::LUGARES as $clave => $coord) {
            if (preg_match('/(^|\s)' . preg_quote($clave, '/') . '(\s|$)/', $primero)) {
                return $coord;
            }
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null */
    public static function porDepartamento(?string $departamento): ?array
    {
        return null === $departamento ? null : (self::DEPARTAMENTOS[self::normalizar($departamento)] ?? null);
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ä' => 'a']);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t;

        return trim($t);
    }
}
