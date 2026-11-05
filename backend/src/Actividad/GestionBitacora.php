<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Entity\ConfiguracionBitacora;
use App\Entity\Empresa;
use App\Entity\Estacion;
use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Ver y cambiar `ConfiguracionBitacora` desde la administración (capacidad
 * `bitacora.configurar`): nivel, familias, alcance y selección de usuarios,
 * retención, hora del resumen y demás. Valida todo antes de guardar.
 */
final class GestionBitacora
{
    public const LIMITES = [
        "retencionMeses" => [1, 60],
        "retencionResumenMeses" => [0, 600],
        "intervaloPantallaSegundos" => [10, 600],
        "maxDetalleKb" => [1, 64],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesBitacora $ajustes,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, mixed> */
    public function datos(): array
    {
        $c = $this->ajustes->actual();

        return [
            "activa" => $c->isActiva(),
            "nivel" => $c->getNivel()->value,
            "familiasApagadas" => array_map(static fn(TipoActividad $t) => $t->value, $c->getFamiliasApagadas()),
            "alcance" => $c->getAlcance(),
            "seleccion" => $this->conEtiquetas($c->getSeleccion()),
            "retencionMeses" => $c->getRetencionMeses(),
            "exportarAntesDeBorrar" => $c->isExportarAntesDeBorrar(),
            "retencionResumenMeses" => $c->getRetencionResumenMeses(),
            "horaResumen" => $c->getHoraResumen(),
            "intervaloPantallaSegundos" => $c->getIntervaloPantallaSegundos(),
            "maxDetalleKb" => $c->getMaxDetalleKb(),
            "camposExtra" => $c->getCamposExtra(),
            "cambiosEntidades" => $c->isCambiosEntidades(),
            "actualizadaEn" => $c->getActualizadaEn()?->format(\DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getLabel(),
            "catalogo" => [
                "niveles" => array_map(static fn(NivelActividad $n) => ["valor" => $n->value, "etiqueta" => $n->etiqueta(), "familias" => array_map(static fn(TipoActividad $t) => $t->value, $n->familias())], NivelActividad::cases()),
                "familias" => array_map(static fn(TipoActividad $t) => ["valor" => $t->value, "etiqueta" => $t->etiqueta()], TipoActividad::cases()),
                "camposBase" => Politica::CAMPOS_BASE,
                "roles" => array_map(static fn(Role $r) => ["id" => (int) $r->getId(), "nombre" => (string) $r->getNombre()], $this->em->getRepository(Role::class)->findBy([], ["nombre" => "ASC"])),
                "empresas" => array_map(static fn(Empresa $e) => ["id" => (int) $e->getId(), "nombre" => (string) ($e->getNombreCorto() ?: $e->getNombre())], $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"])),
                "estaciones" => array_map(static fn(Estacion $e) => ["id" => (int) $e->getId(), "nombre" => (string) $e->getNombre()], $this->em->getRepository(Estacion::class)->findBy([], ["nombre" => "ASC"])),
            ],
        ];
    }

    /**
     * Cambia solo lo que viene.
     *
     * @param array<string, mixed> $datos
     *
     * @throws BitacoraRechazada si algo no es válido (no guarda nada)
     */
    public function guardar(array $datos, ?Usuario $por): ConfiguracionBitacora
    {
        $config = $this->em->find(ConfiguracionBitacora::class, ConfiguracionBitacora::ID);
        if ($config === null) {
            $config = new ConfiguracionBitacora();
            $this->em->persist($config);
        }

        if (array_key_exists("activa", $datos)) {
            $config->setActiva((bool) $datos["activa"]);
        }
        if (array_key_exists("nivel", $datos)) {
            $nivel = NivelActividad::tryFrom((string) $datos["nivel"]) ?? throw new BitacoraRechazada("El nivel debe ser mínimo, medio o máximo.", "nivel_invalido");
            $config->setNivel($nivel);
        }
        if (array_key_exists("familiasApagadas", $datos)) {
            if (!is_array($datos["familiasApagadas"])) {
                throw new BitacoraRechazada("Las familias apagadas deben ser una lista.", "familias_invalidas");
            }
            $familias = [];
            foreach ($datos["familiasApagadas"] as $f) {
                $familias[] = TipoActividad::tryFrom((string) $f) ?? throw new BitacoraRechazada(sprintf("«%s» no es una familia de la bitácora.", (string) $f), "familias_invalidas");
            }
            $config->setFamiliasApagadas($familias);
        }
        if (array_key_exists("alcance", $datos)) {
            if (!in_array($datos["alcance"], [ConfiguracionBitacora::ALCANCE_TODOS, ConfiguracionBitacora::ALCANCE_SELECCIONADOS], true)) {
                throw new BitacoraRechazada("El alcance debe ser «todos» o «seleccionados».", "alcance_invalido");
            }
            $config->setAlcance((string) $datos["alcance"]);
        }
        if (array_key_exists("seleccion", $datos)) {
            try {
                $config->setSeleccion(new Seleccion(Seleccion::validar($datos["seleccion"])));
            } catch (\InvalidArgumentException $e) {
                throw new BitacoraRechazada($e->getMessage(), "seleccion_invalida");
            }
        }
        if ($config->getAlcance() === ConfiguracionBitacora::ALCANCE_SELECCIONADOS && $config->getSeleccion()->vacia() && $config->isActiva()) {
            throw new BitacoraRechazada("Con el alcance «solo seleccionados», elegí al menos un usuario, rol, empresa o estación.", "seleccion_vacia");
        }
        foreach (self::LIMITES as $campo => [$min, $max]) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }
            $valor = filter_var($datos[$campo], FILTER_VALIDATE_INT);
            if ($valor === false || $valor < $min || $valor > $max) {
                throw new BitacoraRechazada(sprintf("«%s» debe ser un número entre %d y %d.", $campo, $min, $max), "valor_invalido");
            }
            match ($campo) {
                "retencionMeses" => $config->setRetencionMeses($valor),
                "retencionResumenMeses" => $config->setRetencionResumenMeses($valor),
                "intervaloPantallaSegundos" => $config->setIntervaloPantallaSegundos($valor),
                "maxDetalleKb" => $config->setMaxDetalleKb($valor),
            };
        }
        if (array_key_exists("exportarAntesDeBorrar", $datos)) {
            $config->setExportarAntesDeBorrar((bool) $datos["exportarAntesDeBorrar"]);
        }
        if (array_key_exists("cambiosEntidades", $datos)) {
            $config->setCambiosEntidades((bool) $datos["cambiosEntidades"]);
        }
        if (array_key_exists("horaResumen", $datos)) {
            $hora = (string) $datos["horaResumen"];
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                throw new BitacoraRechazada("La hora del resumen debe ser HH:MM (00:00 a 23:59).", "hora_invalida");
            }
            $config->setHoraResumen($hora);
        }
        if (array_key_exists("camposExtra", $datos)) {
            $config->setCamposExtra(self::campos($datos["camposExtra"]));
        }

        $config->actualizada($por, \DateTimeImmutable::createFromInterface($this->reloj->now()));
        $this->em->flush();
        $this->ajustes->olvidar();

        return $config;
    }

    /**
     * @return list<string>
     *
     * @throws BitacoraRechazada
     */
    private static function campos(mixed $lista): array
    {
        if (!is_array($lista)) {
            throw new BitacoraRechazada("Los campos extra deben ser una lista.", "campos_invalidos");
        }
        $campos = [];
        foreach ($lista as $c) {
            $c = trim((string) $c);
            if ($c === "") {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,59}$/', $c)) {
                throw new BitacoraRechazada(sprintf("«%s» no es un nombre de campo válido.", $c), "campos_invalidos");
            }
            $campos[] = $c;
        }

        return array_values(array_unique($campos));
    }

    /**
     * La selección con el nombre de cada cosa elegida (para mostrarla).
     *
     * @return list<array{tipo: string, id: int, desde: ?string, hasta: ?string, nombre: string}>
     */
    private function conEtiquetas(Seleccion $seleccion): array
    {
        return array_map(function (array $e): array {
            $nombre = match ($e["tipo"]) {
                "usuario" => $this->em->find(Usuario::class, $e["id"])?->getLabel(),
                "rol" => $this->em->find(Role::class, $e["id"])?->getNombre(),
                "empresa" => ($emp = $this->em->find(Empresa::class, $e["id"])) ? ($emp->getNombreCorto() ?: $emp->getNombre()) : null,
                "estacion" => $this->em->find(Estacion::class, $e["id"])?->getNombre(),
                default => null,
            };

            return [...$e, "nombre" => (string) ($nombre ?? sprintf("%s %d (ya no existe)", $e["tipo"], $e["id"]))];
        }, $seleccion->entradas());
    }
}
