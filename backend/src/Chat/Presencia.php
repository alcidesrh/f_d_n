<?php

declare(strict_types=1);

namespace App\Chat;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Quién tiene la aplicación abierta. Cada pestaña ("conexión") late cada
 * ~30 s; un usuario está en línea mientras alguna de sus conexiones haya
 * latido hace menos de `VIGENCIA` segundos. Nada va a la base de datos: los
 * latidos viven en la caché y se apagan solos.
 *
 * `latir` y `salir` informan si el usuario cambió de estado, para avisarlo
 * al instante (`AvisosChat`); un cierre sin aviso (corte de red, navegador
 * muerto) se nota al vencer el latido, cuando los demás clientes preguntan.
 */
final class Presencia
{
    /** Más de tres latidos: tolera pestañas en segundo plano, donde el navegador frena los temporizadores. */
    public const VIGENCIA = 100;

    public function __construct(private readonly CacheItemPoolInterface $cache) {}

    private static function clave(int $usuario): string
    {
        return "chat_presencia_" . $usuario;
    }

    /** @return array<string, int> conexión => último latido, sin las vencidas */
    private static function vivas(mixed $guardado, int $ahora): array
    {
        return is_array($guardado) ? array_filter($guardado, static fn($t) => is_int($t) && $ahora - $t < self::VIGENCIA) : [];
    }

    /** @return bool true si estaba desconectado y ahora no */
    public function latir(int $usuario, string $conexion): bool
    {
        $ahora = time();
        $item = $this->cache->getItem(self::clave($usuario));
        $vivas = self::vivas($item->get(), $ahora);
        $estabaEnLinea = $vivas !== [];
        $vivas[$conexion] = $ahora;
        $this->cache->save($item->set($vivas)->expiresAfter(self::VIGENCIA));

        return !$estabaEnLinea;
    }

    /** @return bool true si era su última conexión y quedó desconectado */
    public function salir(int $usuario, string $conexion): bool
    {
        $item = $this->cache->getItem(self::clave($usuario));
        $vivas = self::vivas($item->get(), time());
        if ($vivas === []) {
            return false;
        }
        unset($vivas[$conexion]);
        if ($vivas === []) {
            $this->cache->deleteItem(self::clave($usuario));

            return true;
        }
        $this->cache->save($item->set($vivas)->expiresAfter(self::VIGENCIA));

        return false;
    }

    /**
     * @param list<int> $usuarios
     * @return list<int> los que están en línea
     */
    public function enLinea(array $usuarios): array
    {
        $claves = [];
        foreach ($usuarios as $id) {
            $claves[self::clave($id)] = $id;
        }
        $ahora = time();
        $enLinea = [];
        foreach ($this->cache->getItems(array_keys($claves)) as $clave => $item) {
            if ($item->isHit() && self::vivas($item->get(), $ahora) !== []) {
                $enLinea[] = $claves[$clave];
            }
        }

        return $enLinea;
    }
}
