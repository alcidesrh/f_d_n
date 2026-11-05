<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Entity\ConfiguracionBitacora;

/**
 * Qué se registra y de quién, tomado de `ConfiguracionBitacora` (una copia
 * inmutable: se decide sin volver a la base). Puro.
 */
final class Politica
{
    /** Campos del cuerpo de las peticiones que siempre pueden ir al detalle (máximo detalle). */
    public const CAMPOS_BASE = [
        "id", "ids", "salida", "salidas", "asiento", "asientos", "boleto", "boletos", "venta", "ventas",
        "trayecto", "origen", "destino", "sube", "baja", "cliente", "nit", "canal", "motivo", "estacion",
        "empresa", "ruta", "rutas", "bus", "fecha", "desde", "hasta", "estado", "estados", "tipo", "cortesia",
        "encomienda", "encomiendas", "modalidad", "bultos", "agencia", "monto", "importe", "total", "cantidad",
        "page", "itemsPerPage", "order", "q", "buscar", "texto", "filtros", "formato", "reporte", "usuario",
        "rol", "roles", "capacidad", "operationName", "variables", "input", "nombre", "codigo", "accion",
    ];

    /**
     * @param list<TipoActividad> $familiasApagadas
     * @param list<string>        $camposExtra
     */
    public function __construct(
        public readonly bool $activa = true,
        public readonly NivelActividad $nivel = NivelActividad::MEDIO,
        private readonly array $familiasApagadas = [],
        public readonly bool $soloSeleccionados = false,
        public readonly Seleccion $seleccion = new Seleccion([]),
        public readonly int $maxDetalleBytes = 4096,
        private readonly array $camposExtra = [],
        public readonly int $intervaloPantallaSegundos = 30,
        public readonly bool $cambiosEntidades = true,
    ) {}

    public static function desde(ConfiguracionBitacora $c): self
    {
        return new self(
            $c->isActiva(),
            $c->getNivel(),
            $c->getFamiliasApagadas(),
            $c->getAlcance() === ConfiguracionBitacora::ALCANCE_SELECCIONADOS,
            $c->getSeleccion(),
            max(1, $c->getMaxDetalleKb()) * 1024,
            $c->getCamposExtra(),
            $c->getIntervaloPantallaSegundos(),
            $c->isCambiosEntidades(),
        );
    }

    /** La familia está encendida (por la configuración y el nivel). */
    public function registra(TipoActividad $tipo): bool
    {
        return $this->activa && in_array($tipo, $this->nivel->familias(), true) && !in_array($tipo, $this->familiasApagadas, true);
    }

    /**
     * Para decidir si alguien está en la selección hacen falta sus roles o
     * empresas (una consulta de sus asignaciones) solo si la selección los usa.
     */
    public function necesitaPermisos(): bool
    {
        if (!$this->soloSeleccionados) {
            return false;
        }
        foreach ($this->seleccion->entradas() as $e) {
            if ($e["tipo"] === "rol" || $e["tipo"] === "empresa") {
                return true;
            }
        }

        return false;
    }

    /** A este usuario se le sigue la actividad. */
    public function sigue(Sujeto $s, \DateTimeInterface $ahora): bool
    {
        return $this->activa && (!$this->soloSeleccionados || $this->seleccion->incluye($s, $ahora));
    }

    /**
     * Qué fila deja una petición: `peticion`, `sondeo` (agregada) o ninguna.
     */
    public function tipoDePeticion(string $metodo, bool $sondeo): ?TipoActividad
    {
        if ($sondeo) {
            return $this->registra(TipoActividad::SONDEO) ? TipoActividad::SONDEO : null;
        }
        if (in_array(strtoupper($metodo), ["GET", "HEAD", "OPTIONS"], true) && !$this->nivel->lecturas()) {
            return null;
        }

        return $this->registra(TipoActividad::PETICION) ? TipoActividad::PETICION : null;
    }

    public function cuerpos(): bool
    {
        return $this->nivel->cuerpos();
    }

    /** @return list<string> */
    public function camposPermitidos(): array
    {
        return array_values(array_unique([...self::CAMPOS_BASE, ...$this->camposExtra]));
    }
}
