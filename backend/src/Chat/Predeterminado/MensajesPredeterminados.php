<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Chat\Archivos;
use App\Chat\ChatRechazado;
use App\Entity\ChatArchivo;
use App\Entity\ChatCanal;
use App\Entity\Empresa;
use App\Entity\Enum\TipoCanalChat;
use App\Entity\Enum\TipoMensajePredeterminado;
use App\Entity\MensajePredeterminado;
use App\Entity\Ruta;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Administración de las instancias de mensajes predeterminados (ADR-045):
 * validar, guardar, listar y presentar. Lo usa
 * `MensajePredeterminadoController` (`chat.predeterminados`).
 */
final class MensajesPredeterminados
{
    public const MAX_NOMBRE = 120;
    /** Deja lugar al contenido del predeterminado dentro del tope del chat. */
    public const MAX_TEXTO = 2000;
    public const MAX_ARCHIVOS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly Archivos $archivos,
        private readonly Difusion $difusion,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        /** @var list<MensajePredeterminado> $todas */
        $todas = $this->em->getRepository(MensajePredeterminado::class)->findBy([], ["nombre" => "ASC", "id" => "ASC"]);
        $envios = [];
        foreach ($this->db->fetchAllAssociative("SELECT mensaje_id, MAX(enviado_en) AS ultimo, COUNT(*) AS envios FROM envio_mensaje_predeterminado GROUP BY mensaje_id") as $f) {
            $envios[(int) $f["mensaje_id"]] = ["ultimo" => (new \DateTimeImmutable((string) $f["ultimo"]))->format(DATE_ATOM), "envios" => (int) $f["envios"]];
        }

        return array_map(fn(MensajePredeterminado $m) => $this->presentar($m, $envios[(int) $m->getId()] ?? null), $todas);
    }

    /** @return array<string, mixed> catálogo y lo que se elige en el formulario */
    public function opciones(): array
    {
        $usuarios = $this->em->createQuery("SELECT u FROM App\Entity\Usuario u WHERE u.activo = true ORDER BY u.username")->getResult();
        $grupos = $this->em->createQuery("SELECT c FROM App\Entity\ChatCanal c WHERE c.tipo = :grupo ORDER BY c.nombre")
            ->setParameter("grupo", TipoCanalChat::Grupo)->getResult();
        $empresas = $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]);
        $rutas = $this->em->getRepository(Ruta::class)->findBy(["activo" => true], ["nombre" => "ASC"]);

        return [
            "tipos" => array_map(static fn(TipoMensajePredeterminado $t) => [
                "codigo" => $t->value,
                "nombre" => $t->nombre(),
                "descripcion" => $t->descripcion(),
                "exigeEmpresa" => $t->exigeEmpresa(),
                "exigeRuta" => $t->exigeRuta(),
                "conHora" => $t->conHora(),
                "conDias" => $t->conDias(),
                "exigeContenido" => $t->exigeContenido(),
                "esAnomalia" => $t->esAnomalia(),
            ], TipoMensajePredeterminado::cases()),
            "horaCierre" => TipoMensajePredeterminado::HORA_CIERRE,
            "usuarios" => array_map(static fn(Usuario $u) => ["id" => (int) $u->getId(), "label" => $u->getLabel(), "usuario" => $u->getUsername()], $usuarios),
            "grupos" => array_map(static fn(ChatCanal $c) => ["id" => (int) $c->getId(), "nombre" => (string) $c->getNombre()], $grupos),
            "empresas" => array_map(static fn(Empresa $e) => ["id" => (int) $e->getId(), "nombre" => (string) $e->getNombre()], $empresas),
            "rutas" => array_map(static fn(Ruta $r) => ["id" => (int) $r->getId(), "nombre" => $r->getNombre()], $rutas),
        ];
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos, Usuario $yo): MensajePredeterminado
    {
        $tipo = TipoMensajePredeterminado::tryFrom((string) ($datos["tipo"] ?? "")) ?? throw new ChatRechazado("Elija el tipo de mensaje.");
        $m = new MensajePredeterminado("", $tipo, $yo);
        $this->aplicar($m, $datos, $yo);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }

    /** @param array<string, mixed> $datos */
    public function editar(MensajePredeterminado $m, array $datos, Usuario $yo): MensajePredeterminado
    {
        if (isset($datos["tipo"])) {
            $m->setTipo(TipoMensajePredeterminado::tryFrom((string) $datos["tipo"]) ?? throw new ChatRechazado("Tipo de mensaje desconocido."));
        }
        $this->aplicar($m, $datos, $yo);
        $m->tocar();
        $this->em->flush();

        return $m;
    }

    public function activar(MensajePredeterminado $m, bool $activo): MensajePredeterminado
    {
        $m->setActivo($activo);
        $m->tocar();
        $this->em->flush();

        return $m;
    }

    /** Los archivos quedan sueltos: la purga de la madrugada los quita (salvo lo ya enviado). */
    public function eliminar(MensajePredeterminado $m): void
    {
        $this->em->remove($m);
        $this->em->flush();
    }

    /**
     * @param array{ultimo: string, envios: int}|null $envios
     *
     * @return array<string, mixed>
     */
    public function presentar(MensajePredeterminado $m, ?array $envios = null): array
    {
        $tipo = $m->getTipo();

        return [
            "id" => $m->getId(),
            "nombre" => $m->getNombre(),
            "tipo" => $tipo->value,
            "tipoNombre" => $tipo->nombre(),
            "activo" => $m->isActivo(),
            "empresa" => $m->getEmpresa() === null ? null : ["id" => (int) $m->getEmpresa()->getId(), "nombre" => (string) $m->getEmpresa()->getNombre()],
            "ruta" => $m->getRuta() === null ? null : ["id" => (int) $m->getRuta()->getId(), "nombre" => $m->getRuta()->getNombre()],
            "hora" => $m->getHora() ?? ($tipo->conHora() && $tipo !== TipoMensajePredeterminado::LIBRE ? TipoMensajePredeterminado::HORA_CIERRE : null),
            "dias" => $m->getDias(),
            "texto" => $m->getTexto(),
            "archivos" => array_map(fn(ChatArchivo $a) => $this->archivos->presentar($a), $m->getArchivos()->toArray()),
            "usuarios" => array_map(static fn(Usuario $u) => ["id" => (int) $u->getId(), "label" => $u->getLabel()], $m->getUsuarios()->toArray()),
            "grupos" => array_map(static fn(ChatCanal $c) => ["id" => (int) $c->getId(), "nombre" => (string) $c->getNombre()], $m->getGrupos()->toArray()),
            "destinatarios" => count($this->difusion->audiencia($m)),
            "ultimoEnvio" => $envios["ultimo"] ?? null,
            "envios" => $envios["envios"] ?? 0,
            "creadoPor" => $m->getCreadoPor()?->getLabel(),
            "actualizadoEn" => $m->getActualizadoEn()->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $d */
    private function aplicar(MensajePredeterminado $m, array $d, Usuario $yo): void
    {
        $tipo = $m->getTipo();

        $nombre = trim((string) ($d["nombre"] ?? $m->getNombre()));
        if ($nombre === "") {
            throw new ChatRechazado("Póngale un nombre a la instancia.");
        }
        if (mb_strlen($nombre) > self::MAX_NOMBRE) {
            throw new ChatRechazado(sprintf("El nombre no puede pasar de %d caracteres.", self::MAX_NOMBRE));
        }
        $m->setNombre($nombre);
        if (array_key_exists("activo", $d)) {
            $m->setActivo((bool) $d["activo"]);
        }

        $empresa = array_key_exists("empresa", $d) ? $this->buscar(Empresa::class, $d["empresa"], "La empresa no existe.") : $m->getEmpresa();
        if ($tipo->exigeEmpresa() && $empresa === null) {
            throw new ChatRechazado("Elija la empresa.");
        }
        // Las anomalías la admiten para filtrar; el mensaje con horario no la usa.
        $m->setEmpresa($tipo === TipoMensajePredeterminado::LIBRE ? null : $empresa);

        $ruta = array_key_exists("ruta", $d) ? $this->buscar(Ruta::class, $d["ruta"], "La ruta no existe.") : $m->getRuta();
        if ($tipo->exigeRuta() && $ruta === null) {
            throw new ChatRechazado("Elija la ruta.");
        }
        $m->setRuta($tipo->exigeRuta() ? $ruta : null);

        $hora = array_key_exists("hora", $d) ? self::hora($d["hora"]) : $m->getHora();
        if ($tipo === TipoMensajePredeterminado::LIBRE && $hora === null) {
            throw new ChatRechazado("Elija la hora a la que sale.");
        }
        $m->setHora($tipo->conHora() ? $hora : null);

        $dias = array_key_exists("dias", $d) ? self::dias($d["dias"]) : $m->getDias();
        $m->setDias($tipo->conDias() ? $dias : []);

        $texto = array_key_exists("texto", $d) ? trim((string) $d["texto"]) : (string) $m->getTexto();
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            throw new ChatRechazado(sprintf("El texto no puede pasar de %d caracteres.", self::MAX_TEXTO));
        }
        $m->setTexto($texto === "" ? null : $texto);

        if (array_key_exists("archivos", $d)) {
            $m->setArchivos($this->archivosDe($m, (array) $d["archivos"], $yo));
        }
        if ($tipo->exigeContenido() && $m->getTexto() === null && $m->getArchivos()->isEmpty()) {
            throw new ChatRechazado("Escriba un texto o adjunte una imagen o un archivo.");
        }

        if (array_key_exists("usuarios", $d)) {
            $ids = self::ids($d["usuarios"]);
            $usuarios = $ids === [] ? [] : $this->em->getRepository(Usuario::class)->findBy(["id" => $ids]);
            if (count($usuarios) !== count($ids)) {
                throw new ChatRechazado("Algún usuario no existe.");
            }
            $m->setUsuarios($usuarios);
        }
        if (array_key_exists("grupos", $d)) {
            $ids = self::ids($d["grupos"]);
            $grupos = $ids === [] ? [] : $this->em->getRepository(ChatCanal::class)->findBy(["id" => $ids, "tipo" => TipoCanalChat::Grupo]);
            if (count($grupos) !== count($ids)) {
                throw new ChatRechazado("Algún grupo no existe.");
            }
            $m->setGrupos($grupos);
        }
        if ($m->getUsuarios()->isEmpty() && $m->getGrupos()->isEmpty()) {
            throw new ChatRechazado("Elija a quién va dirigido: usuarios o grupos.");
        }
    }

    /**
     * Los que ya tenía la instancia se conservan; los nuevos tienen que ser
     * subidos por quien edita y no estar en ningún mensaje.
     *
     * @param list<mixed> $valores
     *
     * @return list<ChatArchivo>
     */
    private function archivosDe(MensajePredeterminado $m, array $valores, Usuario $yo): array
    {
        $ids = self::ids($valores);
        if (count($ids) > self::MAX_ARCHIVOS) {
            throw new ChatRechazado(sprintf("A lo sumo %d archivos.", self::MAX_ARCHIVOS));
        }
        $propios = [];
        foreach ($m->getArchivos() as $a) {
            $propios[(int) $a->getId()] = $a;
        }
        $nuevos = $this->archivos->sueltosDe(array_values(array_diff($ids, array_keys($propios))), $yo);
        foreach ($nuevos as $a) {
            $propios[(int) $a->getId()] = $a;
        }

        return array_values(array_filter(array_map(static fn(int $id) => $propios[$id] ?? null, $ids)));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $clase
     *
     * @return T|null
     */
    private function buscar(string $clase, mixed $id, string $error): ?object
    {
        $id = is_array($id) ? ($id["id"] ?? null) : $id;
        if ($id === null || $id === "") {
            return null;
        }

        return $this->em->find($clase, (int) $id) ?? throw new ChatRechazado($error);
    }

    private static function hora(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === "") {
            return null;
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) !== 1) {
            throw new ChatRechazado("La hora va como HH:MM (00:00 a 23:59).");
        }

        return $valor;
    }

    /** @return list<int> */
    private static function dias(mixed $valor): array
    {
        $dias = self::ids($valor);
        foreach ($dias as $d) {
            if ($d < 1 || $d > 7) {
                throw new ChatRechazado("Los días van del 1 (lunes) al 7 (domingo).");
            }
        }
        sort($dias);

        return $dias;
    }

    /** @return list<int> */
    private static function ids(mixed $valor): array
    {
        if (!is_array($valor)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn(mixed $v) => (int) (is_array($v) ? ($v["id"] ?? 0) : $v), $valor)));
    }
}
