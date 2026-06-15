<?php

namespace App\Migration;

class Mapeador {
    /**
     * Static data with numeric old PK → use old ID as new PK, no legacy_id.
     */
    public function empresa(array $old): array {
        return [
            'id' => (int) $old['id'],
            'nombre' => $this->truncate($old['nombre'] ?? '', 255),
            'nif' => $this->truncate($old['nit'] ?? null, 20),
            'direccion' => $this->truncate($old['direccion'] ?? null, 255),
            'telefono' => $this->truncate($old['telefonos'] ?? null, 20),
            'email' => null,
        ];
    }

    public function estacion(array $old): array {
        return [
            'id' => (int) $old['id'],
            'nombre' => $this->truncate($old['nombre'] ?? '', 255),
            'direccion' => $this->truncate($old['direccion'] ?? null, 255),
            'latitud' => $old['latitude'] ?? null,
            'longitud' => $old['longitude'] ?? null,
        ];
    }

    /**
     * Bus old PK is string (codigo) → keep legacy_id.
     */
    public function bus(array $old, int $empresaId): array {
        return [
            'matricula' => $this->truncate($old['placa'] ?? '', 50),
            // `gama` se resuelve en el migrador a partir de la descripción del tipo
            'gama' => null,
            'empresa_id' => $empresaId,
            // keep legacy string PK
            'codigo' => $this->truncate($old['codigo'] ?? '', 15),

            'marca_id' => isset($old['marca_id']) ? (int) $old['marca_id'] : null,
            'piloto_id' => isset($old['piloto_id']) ? (int) $old['piloto_id'] : null,
            'piloto_aux_id' => isset($old['piloto_aux_id']) ? (int) $old['piloto_aux_id'] : null,

            'anoFabricacion' => isset($old['anoFabricacion']) ? (int) $old['anoFabricacion'] : 0,
            'numeroSeguro' => $this->truncate($old['numeroSeguro'] ?? null, 30),
            'fechaVencimientoTarjetaOperaciones' => $this->formatDate($old['fechaVencimientoTarjetaOperaciones'] ?? null),
            'numeroTarjetaRodaje' => $this->truncate($old['numeroTarjetaRodaje'] ?? null, 50),
            'numeroTarjetaOperaciones' => $this->truncate($old['numeroTarjetaOperaciones'] ?? null, 50),
            'descripcion' => $this->truncate($old['descripcion'] ?? null, 65535),
        ];
    }

    /**
     * Asiento old PK is numeric → use as new PK, no legacy_id.
     */
    public function asiento(array $old, int $busId): array {
        $clase = match ((int) ($old['clase_id'] ?? 0)) {
            2 => 'B',
            default => 'A',
        };

        return [
            'id' => (int) $old['id'],
            'numero' => (int) ($old['numero'] ?? 0),
            'clase' => $clase,
            'fila' => (int) ($old['coordenadaY'] ?? 0),
            'columna' => (int) ($old['coordenadaX'] ?? 0),
            'bus_id' => $busId,
        ];
    }

    /**
     * Cliente old PK is numeric → use as new PK, no legacy_id.
     */
    public function cliente(array $old): array {
        $nombres = array_filter([
            $old['nombre'] ?? '',
            $old['nombre1'] ?? '',
            $old['nombre2'] ?? '',
        ]);
        $apellidos = array_filter([
            $old['apellido1'] ?? '',
            $old['apellido2'] ?? '',
        ]);

        return [
            'id' => (int) $old['id'],
            'nombre' => $this->truncate(implode(' ', $nombres) ?: 'S/N', 255),
            'apellido' => $this->truncate(implode(' ', $apellidos) ?: 'S/N', 50),
            'nit' => $this->truncate($old['nit'] ?? null, 20),
            'email' => $this->truncate($old['correo'] ?? null, 50),
            'telefono' => $this->truncate($old['telefono'] ?? null, 15),
        ];
    }

    /**
     * Usuario old PK is numeric → use as new PK, no legacy_id.
     */
    public function usuario(array $old): array {
        return [
            'id' => (int) $old['id'],
            'nombre' => $this->truncate($old['names'] ?? '', 255),
            'username' => $this->truncate($old['username'] ?? '', 180),
            'apellido' => $this->truncate($old['surnames'] ?? '', 50),
            'email' => $this->truncate($old['email'] ?? null, 50),
            'telefono' => $this->truncate($old['phone'] ?? null, 15),
            'password' => $old['password'] ?? null,
            'created_at' => $this->formatDatetime($old['dateCreate'] ?? null),
            'updated_at' => $this->formatDatetime($old['dateLastUdate'] ?? null),
        ];
    }

    public function piloto(array $old): array {
        return [
            'id' => (int) $old['id'],
            'nombre' => $this->truncate($old['nombre'] ?? '', 255),
            'apellido' => $this->truncate($old['apellidos'] ?? '', 50),
            'fechaNacimiento' => $this->formatDate($old['fechaNacimiento'] ?? null),
            'telefono' => $this->truncate($old['phone'] ?? null, 15),
            'codigo' => $this->truncate($old['codigo'] ?? (string) $old['id'], 10),
            'numeroLicencia' => $old['numeroLicencia'] ?? null,
            'fechaVencimientoLicencia' => $this->formatDate($old['fechaVencimientoLicencia'] ?? null),
            'dpi' => $this->truncate($old['dpi'] ?? null, 15),
            'seguroSocial' => $this->truncate($old['seguroSocial'] ?? null, 255),
            'empresa_id' => $old['empresa_id'] ? (int) $old['empresa_id'] : null,
            'nit' => $this->truncate($old['nit'] ?? null, 15),
            'status_id' => null,
        ];
    }

    /**
     * Trayecto old PK is string (ruta.codigo) → keep legacy_id.
     */
    public function trayecto(array $oldRuta, int $origenId, int $destinoId, bool $esRuta): array {
        return [
            'origen_id' => $origenId,
            'destino_id' => $destinoId,
            'distancia_km' => $esRuta ? ($oldRuta['kilometros'] ?? null) : null,
            'duracion_estimada_minutos' => null,
            'activo' => $this->toBool($esRuta ? ($oldRuta['activo'] ?? true) : true),
            'es_ruta' => $this->toBool($esRuta),
            'legacy_id' => $esRuta ? $oldRuta['codigo'] : null,
        ];
    }

    /**
     * Salida is variable data → keep legacy_id.
     */
    public function salida(array $old, ?int $rutaId, ?int $busId, int $empresaId, ?int $tarifaId): array {
        $cancelada = (int) ($old['estado_id'] ?? 0) === 4;

        return [
            'hora_partida' => $this->formatDatetime($old['fecha']),
            'ruta_id' => $rutaId,
            'bus_id' => $busId,
            'empresa_id' => $empresaId,
            'tarifa_id' => $tarifaId,
            'activa' => $this->toBool(!$cancelada),
            'legacy_id' => (string) $old['id'],
        ];
    }

    /**
     * Boleto is variable data → keep legacy_id.
     */
    public function boleto(array $old, int $salidaId, ?int $clienteId, int $estacionId, ?int $trayectoId, ?int $asientoId, ?int $usuarioId): array {
        return [
            'fecha_compra' => $this->formatDatetime($old['fecha_creacion']),
            'salida_id' => $salidaId,
            'trayecto_id' => $trayectoId,
            'cliente_id' => $clienteId,
            'estacion_id' => $estacionId,
            'usuario_creador' => $usuarioId,
            'legacy_id' => (string) $old['id'],
        ];
    }

    /**
     * Tarifa old PK is numeric → use as new PK, no legacy_id.
     */
    public function tarifa(array $old, int $empresaId): array {
        $nombre = sprintf('Tarifa-%s-%s-%s', $old['estacion_origen_id'] ?? '?', $old['estacion_destino_id'] ?? '?', $old['id']);

        return [
            'id' => (int) $old['id'],
            'nombre' => $nombre,
            'precio_clase_a' => $old['tarifaValor'] ?? '0',
            'precio_clase_b' => null,
            'empresa_id' => $empresaId,
            'bus_id' => null,
        ];
    }

    private function truncate(?string $value, int $maxLength): ?string {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }

    private function toBool(bool $value): string {
        return $value ? '1' : '0';
    }

    private function formatDatetime(mixed $value): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && $value !== '') {
            $normalized = preg_replace('/:([AP]M)$/i', ' $1', $value);
            $dt = date_create($normalized);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
            return null;
        }

        return null;
    }

    private function formatDate(mixed $value): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && $value !== '') {
            $normalized = preg_replace('/:([AP]M)$/i', ' $1', $value);
            $dt = date_create($normalized);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
            return null;
        }

        return null;
    }
}
