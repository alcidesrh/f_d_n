<?php

namespace App\Migration;

class Mapeador {
    public function empresa(array $old): array {
        $telefono = isset($old['telefonos']) ? mb_substr($old['telefonos'], 0, 20) : null;

        return [
            'nombre' => $old['nombre'] ?? '',
            'nif' => $old['nit'] ?? null,
            'direccion' => $old['direccion'] ?? null,
            'telefono' => $telefono,
            'email' => null,
            'legacy_id' => (string) $old['id'],
        ];
    }

    public function estacion(array $old): array {
        return [
            'nombre' => $old['nombre'] ?? '',
            'direccion' => $old['direccion'] ?? null,
            'latitud' => $old['latitude'] ?? null,
            'longitud' => $old['longitude'] ?? null,
            'legacy_id' => (string) $old['id'],
        ];
    }

    public function bus(array $old, int $empresaId): array {
        return [
            'matricula' => $old['placa'] ?? '',
            'gama' => null,
            'empresa_id' => $empresaId,
            'legacy_id' => $old['codigo'],
        ];
    }

    public function asiento(array $old, int $busId): array {
        $clase = match ((int) ($old['clase_id'] ?? 0)) {
            2 => 'B',
            default => 'A',
        };

        return [
            'numero' => (int) ($old['numero'] ?? 0),
            'clase' => $clase,
            'fila' => (int) ($old['coordenadaY'] ?? 0),
            'columna' => (int) ($old['coordenadaX'] ?? 0),
            'bus_id' => $busId,
            'legacy_id' => (string) $old['id'],
        ];
    }

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
            'nombre' => implode(' ', $nombres) ?: 'S/N',
            'apellido' => implode(' ', $apellidos) ?: 'S/N',
            'nit' => $old['nit'] ?? null,
            'email' => $old['correo'] ?? null,
            'telefono' => $old['telefono'] ?? null,
            'legacy_id' => (string) $old['id'],
        ];
    }

    public function usuario(array $old): array {

        return [
            'nombre' => $old['names'],
            'username' => $old['username'],
            'apellido' => $old['surnames'],
            'email' => $old['email'] ?? null,
            'telefono' => $old['phone'] ?? null,
            'password' => $old['password'] ?? null,
            'created_at' => $old['dateCreate'] ?? null,
            'updated_at' => $old['dateLasUpdate'] ?? null,
            'legacy_id' => (string) $old['id'],
        ];
    }

    public function trayecto(array $oldRuta, int $origenId, int $destinoId, bool $esRuta): array {
        return [
            'origen_id' => $origenId,
            'destino_id' => $destinoId,
            'distancia_km' => $esRuta ? ($oldRuta['kilometros'] ?? null) : null,
            'duracion_estimada_minutos' => null,
            'activo' => $esRuta ? (bool) ($oldRuta['activo'] ?? true) : true,
            'es_ruta' => $esRuta,
            'legacy_id' => $esRuta ? $oldRuta['codigo'] : null,
        ];
    }

    public function salida(array $old, ?int $rutaId, ?int $busId, int $empresaId, ?int $tarifaId): array {
        $cancelada = (int) ($old['estado_id'] ?? 0) === 4;

        return [
            'hora_partida' => $this->formatDatetime($old['fecha']),
            'ruta_id' => $rutaId,
            'bus_id' => $busId,
            'empresa_id' => $empresaId,
            'tarifa_id' => $tarifaId,
            'activa' => !$cancelada,
            'legacy_id' => (string) $old['id'],
        ];
    }

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

    public function tarifa(array $old, int $empresaId): array {
        $nombre = sprintf('Tarifa-%s-%s-%s', $old['estacion_origen_id'] ?? '?', $old['estacion_destino_id'] ?? '?', $old['id']);

        return [
            'nombre' => $nombre,
            'precio_clase_a' => $old['tarifaValor'] ?? '0',
            'precio_clase_b' => null,
            'empresa_id' => $empresaId,
            'bus_id' => null,
            'legacy_id' => (string) $old['id'],
        ];
    }

    private function formatDatetime(mixed $value): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
