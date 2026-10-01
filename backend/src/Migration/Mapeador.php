<?php

namespace App\Migration;

use App\Croquis\Croquis;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\TipoBusSenal;

class Mapeador
{
    /**
     * Static data with numeric old PK → use old ID as new PK, no legacy_id.
     */
    public function empresa(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 255),
            "nit" => $this->truncate($old["nit"] ?? null, 20),
            "direccion" => $this->truncate($old["direccion"] ?? null, 255),
            "telefono" => $this->truncate($old["telefonos"] ?? null, 20),
            "email" => null,
        ];
    }

    public function estacion(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 255),
            "direccion" => $this->truncate($old["direccion"] ?? null, 255),
            "latitud" => $old["latitude"] ?? null,
            "longitud" => $old["longitude"] ?? null,
        ];
    }

    /**
     * Bus old PK is string (codigo) → keep legacy_id.
     */
    public function bus(array $old, int $empresaId): array
    {
        return [
            "matricula" => $this->truncate($old["placa"] ?? "", 50),
            // `gama` se resuelve en el migrador a partir de la descripción del tipo
            "gama" => null,
            "empresa_id" => $empresaId,
            // keep legacy string PK
            "codigo" => $this->truncate($old["codigo"] ?? "", 15),

            "marca_id" => isset($old["marca_id"])
                ? (int) $old["marca_id"]
                : null,
            "piloto_id" => isset($old["piloto_id"])
                ? (int) $old["piloto_id"]
                : null,
            "copiloto_id" => isset($old["piloto_aux_id"])
                ? (int) $old["piloto_aux_id"]
                : null,

            "anoFabricacion" => isset($old["anoFabricacion"])
                ? (int) $old["anoFabricacion"]
                : 0,
            "numeroSeguro" => $this->truncate($old["numeroSeguro"] ?? null, 30),
            "fechaVencimientoTarjetaOperaciones" => $this->formatDate(
                $old["fechaVencimientoTarjetaOperaciones"] ?? null,
            ),
            "numeroTarjetaRodaje" => $this->truncate(
                $old["numeroTarjetaRodaje"] ?? null,
                50,
            ),
            "numeroTarjetaOperaciones" => $this->truncate(
                $old["numeroTarjetaOperaciones"] ?? null,
                50,
            ),
            "descripcion" => $this->truncate(
                $old["descripcion"] ?? null,
                65535,
            ),
        ];
    }

    /**
     * Asiento de un bus. En el legado los asientos son del tipo de bus
     * (`bus_asiento.tipoBus_id`), compartidos por todos los buses de ese tipo:
     * cada bus recibe su copia, con id nuevo (se reconoce por `bus_id` +
     * `numero`). Coordenadas del legado (0, 50, …) → desde 1 (`X / 50 + 1`).
     */
    public function asiento(array $old, int $busId): array
    {
        $clase = match ((int) ($old["clase_id"] ?? 0)) {
            2 => "B",
            default => "A",
        };

        return [
            "numero" => (int) ($old["numero"] ?? 0),
            "clase" => $clase,
            "planta" => $this->planta($old["nivel2"] ?? null),
            "fila" => Croquis::desdeLegado($old["coordenadaY"] ?? 0),
            "columna" => Croquis::desdeLegado($old["coordenadaX"] ?? 0),
            "bus_id" => $busId,
        ];
    }

    /**
     * Señal del croquis (chofer, puerta) de un bus, desde `bus_senal` +
     * `bus_senal_tipo.nombre` (`tipo_nombre`). Null si el tipo no se reconoce.
     */
    public function senal(array $old, int $busId): ?array
    {
        $tipo = TipoBusSenal::desdeNombreLegado($old["tipo_nombre"] ?? null);
        if ($tipo === null) {
            return null;
        }

        return [
            "tipo" => $tipo->value,
            "planta" => $this->planta($old["nivel2"] ?? null),
            "fila" => Croquis::desdeLegado($old["coordenada_y"] ?? 0),
            "columna" => Croquis::desdeLegado($old["coordenada_x"] ?? 0),
            "bus_id" => $busId,
        ];
    }

    /** `nivel2` del legado → planta (1 = baja, 2 = alta). */
    private function planta(mixed $nivel2): int
    {
        return filter_var($nivel2, FILTER_VALIDATE_BOOL) ? 2 : 1;
    }

    /**
     * Cliente old PK is numeric → use as new PK, no legacy_id.
     */
    public function cliente(array $old): array
    {
        $nombres = array_filter([
            $old["nombre"] ?? "",
            $old["nombre1"] ?? "",
            $old["nombre2"] ?? "",
        ]);
        $apellidos = array_filter([
            $old["apellido1"] ?? "",
            $old["apellido2"] ?? "",
        ]);

        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate(implode(" ", $nombres) ?: "S/N", 255),
            "apellido" => $this->truncate(
                implode(" ", $apellidos) ?: "S/N",
                50,
            ),
            "nit" => $this->truncate($old["nit"] ?? null, 20),
            "email" => $this->truncate($old["correo"] ?? null, 50),
            "telefono" => $this->truncate($old["telefono"] ?? null, 15),
            "numero_documento" => $this->truncate($old["dpi"] ?? null, 40),
            // Se validan contra los catálogos ya migrados en el migrador (FK).
            "tipo_documento_id" => self::idONulo($old["tipo_documento_id"] ?? $old["tipoDocumento_id"] ?? null),
            "nacionalidad_id" => self::idONulo($old["nacionalidad_id"] ?? null),
        ];
    }

    /** `tipo_pago` del legado (se conserva el id). */
    public function tipoPago(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 50),
            "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
        ];
    }

    /** `moneda` del legado (se conserva el id; `sigla` = ISO 4217). */
    public function moneda(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "sigla" => strtoupper($this->truncate($old["sigla"] ?? "", 3)),
            "nombre" => $this->truncate($old["nombre"] ?? "", 40),
            "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
        ];
    }

    /** `tipo_documento` del legado (se conserva el id). */
    public function tipoDocumento(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "sigla" => $this->truncate($old["sigla"] ?? null, 3),
            "nombre" => $this->truncate($old["nombre"] ?? "", 50),
            "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
        ];
    }

    /**
     * `nacionalidad` del legado → `Nacion` (tabla `pais`), conservando el id
     * para que `cliente.nacionalidad_id` apunte igual.
     */
    public function nacionalidad(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 255),
            "legacy_id" => "nacionalidad-" . (int) $old["id"],
        ];
    }

    /**
     * Estación del legado con `tipoEstacion_id = 4` → `Agencia` (se conserva
     * el id). El porcentaje de bonificación del legado se guarda como
     * fracción o como porcentaje según la estación: ≤ 1 se toma como fracción.
     *
     * @param array<string, mixed> $old fila de `estacion` (+ `moneda_sigla`)
     */
    public function agencia(array $old): array
    {
        $porcentaje = $old["agencia_porciento_bonificacion"] ?? null;
        if ($porcentaje !== null && $porcentaje !== "") {
            $porcentaje = (float) $porcentaje;
            $porcentaje = $porcentaje > 0 && $porcentaje <= 1 ? $porcentaje * 100 : $porcentaje;
            $porcentaje = number_format(min(max($porcentaje, 0), 100), 2, ".", "");
        } else {
            $porcentaje = null;
        }

        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 255),
            "direccion" => $this->truncate($old["direccion"] ?? null, 255),
            "saldo" => (int) round(((float) ($old["agencia_saldo"] ?? 0)) * 100),
            "moneda" => strtoupper($this->truncate($old["moneda_sigla"] ?? null, 3) ?: "GTQ"),
            "porcentaje_bonificacion" => $porcentaje,
            "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
            "legacy_id" => "estacion-" . (int) $old["id"],
        ];
    }

    /**
     * `factura_emisor` del legado: afiliación al IVA y credenciales del
     * certificador (Forcon) de la empresa.
     *
     * @return array{afiliacion_iva: ?string, usuario: ?string, clave: ?string}
     */
    public function emisorFel(array $old): array
    {
        $texto = static fn(mixed $v) => ($v = trim((string) $v)) !== "" ? $v : null;

        return [
            "afiliacion_iva" => ($a = $texto($old["afiliacion_iva"] ?? null)) !== null ? strtoupper(mb_substr($a, 0, 5)) : null,
            "usuario" => $texto($old["user_forcon"] ?? $old["userForcon"] ?? null),
            "clave" => $texto($old["password_forcon"] ?? $old["passwordForcon"] ?? null),
        ];
    }

    private static function idONulo(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    /**
     * Usuario old PK is numeric → use as new PK, no legacy_id.
     */
    public function usuario(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["names"] ?? "", 255),
            "username" => $this->truncate($old["username"] ?? "", 180),
            "apellido" => $this->truncate($old["surnames"] ?? "", 50),
            "email" => $this->truncate($old["email"] ?? null, 50),
            "telefono" => $this->truncate($old["phone"] ?? null, 15),
            "password" => isset($old["password"], $old["salt"])
                ? '$sha512$' . $old["password"] . '$' . $old["salt"]
                : $old["password"] ?? null,
            "created_at" => $this->formatDatetime($old["dateCreate"] ?? null),
            "updated_at" => $this->formatDatetime(
                $old["dateLastUdate"] ?? null,
            ),
        ];
    }

    public function piloto(array $old): array
    {
        return [
            "id" => (int) $old["id"],
            "nombre" => $this->truncate($old["nombre"] ?? "", 255),
            "apellido" => $this->truncate($old["apellidos"] ?? "", 50),
            "fechaNacimiento" => $this->formatDate(
                $old["fechaNacimiento"] ?? null,
            ),
            "telefono" => $this->truncate($old["phone"] ?? null, 15),
            "codigo" => $this->truncate(
                $old["codigo"] ?? (string) $old["id"],
                10,
            ),
            "numeroLicencia" => $old["numeroLicencia"] ?? null,
            "fechaVencimientoLicencia" => $this->formatDate(
                $old["fechaVencimientoLicencia"] ?? null,
            ),
            "dpi" => $this->truncate($old["dpi"] ?? null, 15),
            "seguroSocial" => $this->truncate(
                $old["seguroSocial"] ?? null,
                255,
            ),
            "empresa_id" => $old["empresa_id"]
                ? (int) $old["empresa_id"]
                : null,
            "nit" => $this->truncate($old["nit"] ?? null, 15),
            "status_id" => null,
        ];
    }

    /**
     * Trayecto old PK is string (ruta.codigo) → keep legacy_id.
     */
    public function trayecto(
        array $oldRuta,
        int $origenId,
        int $destinoId,
        bool $esRuta,
    ): array {
        return [
            "origen_id" => $origenId,
            "destino_id" => $destinoId,
            "distancia_km" => $esRuta ? $oldRuta["kilometros"] ?? null : null,
            "duracion_estimada_minutos" => null,
            "activo" => $this->toBool(
                $esRuta ? $oldRuta["activo"] ?? true : true,
            ),
            "legacy_id" => $esRuta ? $oldRuta["codigo"] : null,
        ];
    }

    /**
     * Salida is variable data → keep legacy_id.
     * `estado` fija PROGRAMADA: `fetchSalidas`/`fetchSalidasVentana` solo traen
     * salidas legacy con estado_id 1 o 2 (Emitido/Chequeado), es decir, salidas
     * legacy aún no completadas — PROGRAMADA es su equivalente razonable en el
     * enum nuevo (ver EstadoSalida).
     */
    public function salida(
        array $old,
        ?int $busId,
        ?int $empresaId,
        ?int $trayectoId = null,
    ): array {
        return [
            "fecha" => $this->formatDatetime($old["fecha"]),
            "bus_id" => $busId,
            "empresa_id" => $empresaId,
            "trayecto_id" => $trayectoId,
            "estado" => EstadoSalida::PROGRAMADA->value,
            "legacy_id" => (string) $old["id"],
        ];
    }

    /**
     * BoletoAsiento is variable data → keep legacy_id.
     * Payload mínimo y desacoplado: cada boleto legacy se convierte en un
     * asiento vendido, enlazado a su venta, salida, cliente y trayecto.
     */
    public function boletoAsiento(
        array $old,
        int $salidaId,
        int $asientoId,
        int $clienteId,
        int $trayectoId,
        string $estado,
        int $boletoVentaId,
    ): array {
        $precio = (int) (($old["precioCalculado"] ?? 0) * 100);

        return [
            "salida_id" => $salidaId,
            "asiento_id" => $asientoId,
            "cliente_id" => $clienteId,
            "trayecto_id" => $trayectoId,
            "estado" => $estado,
            "boleto_venta_id" => $boletoVentaId,
            "precio_monto" => $precio ?: 0,
            "precio_moneda" => "GTQ",
            "legacy_id" => (string) $old["id"],
        ];
    }

    /**
     * BoletoVenta wrapper for a boleto (1:1:1 con BoletoAsiento).
     */
    public function boletoVenta(int $usuarioId): array
    {
        return [
            "usuario_id" => $usuarioId,
        ];
    }

    /**
     * BoletoTarifa old PK is numeric → use as new PK, no legacy_id.
     * `clase_asiento` legacy: 1 = A, 2 = B (ver ClaseAsiento::A/B).
     */
    public function boletoTarifa(
        array $old,
        int $empresaId,
        string $clase,
        int $usuarioId,
        int $trayectoId,
    ): array {
        $nombre = sprintf(
            "Tarifa-%s-%s-%s",
            $old["estacion_origen_id"] ?? "?",
            $old["estacion_destino_id"] ?? "?",
            $old["id"],
        );

        return [
            "id" => (int) $old["id"],
            "nombre" => $nombre,
            "precio_monto" => (int) (($old["tarifaValor"] ?? 0) * 100),
            "precio_moneda" => "GTQ",
            "clase" => $clase,
            "empresa_id" => $empresaId,
            "bus_id" => null,
            "trayecto_id" => $trayectoId,
            "usuario_id" => $usuarioId,
        ];
    }

    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) > $maxLength
            ? mb_substr($value, 0, $maxLength)
            : $value;
    }

    private function toBool(bool $value): string
    {
        return $value ? "1" : "0";
    }

    private function formatDatetime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format("Y-m-d H:i:s");
        }
        if (is_string($value) && $value !== "") {
            $normalized = preg_replace('/:([AP]M)$/i', ' $1', $value);
            $dt = date_create($normalized);
            if ($dt !== false) {
                return $dt->format("Y-m-d H:i:s");
            }
            return null;
        }

        return null;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format("Y-m-d");
        }
        if (is_string($value) && $value !== "") {
            $normalized = preg_replace('/:([AP]M)$/i', ' $1', $value);
            $dt = date_create($normalized);
            if ($dt !== false) {
                return $dt->format("Y-m-d");
            }
            return null;
        }

        return null;
    }
}
