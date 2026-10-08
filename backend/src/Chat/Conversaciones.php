<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Tarjeta\Tarjetas;
use App\Cuenta\FotoPerfil;
use App\Entity\ChatArchivo;
use App\Entity\ChatCanal;
use App\Entity\ChatMensaje;
use App\Entity\ChatMiembro;
use App\Entity\Enum\TipoCanalChat;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Casos de uso del chat interno: directorio de contactos, bandeja, abrir
 * conversaciones, leer, escribir y compartir registros. Todo se mira desde
 * el usuario de la sesión (`$yo`): solo ve los canales donde es miembro.
 */
final class Conversaciones
{
    public const MAX_TEXTO = 4000;
    public const MAX_NOMBRE = 80;
    public const PAGINA = 40;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Tarjetas $tarjetas,
        private readonly AvisosChat $avisos,
        private readonly Archivos $archivos,
        private readonly FotoPerfil $fotos,
    ) {}

    private function perfil(Usuario $u): Perfil
    {
        return Perfil::de($u, $this->fotos);
    }

    /** @return list<array<string, mixed>> con quién puede hablar `$yo`, por nombre */
    public function contactos(Usuario $yo): array
    {
        $usuarios = $this->em->createQuery(
            "SELECT u, a, e, emp FROM App\Entity\Usuario u LEFT JOIN u.agencia a LEFT JOIN u.estacion e LEFT JOIN u.empresa emp WHERE u.id != :yo",
        )->setParameter("yo", $yo->getId())->getResult();
        $mio = $this->perfil($yo);
        $perfiles = array_values(array_filter(
            array_map($this->perfil(...), $usuarios),
            static fn(Perfil $p) => Directorio::puedenConversar($mio, $p),
        ));
        usort($perfiles, static fn(Perfil $a, Perfil $b) => strcasecmp($a->nombre, $b->nombre));

        return array_map(static fn(Perfil $p) => $p->aArray(), $perfiles);
    }

    /** @return array<string, mixed> `{ yo, canales }`, el de actividad más reciente primero */
    public function bandeja(Usuario $yo): array
    {
        /** @var list<ChatCanal> $canales */
        $canales = $this->em->createQuery(
            "SELECT c, m, u FROM App\Entity\ChatCanal c JOIN c.miembros yo WITH yo.usuario = :yo JOIN c.miembros m JOIN m.usuario u ORDER BY c.actividad DESC",
        )->setParameter("yo", $yo->getId())->getResult();
        if ($canales === []) {
            return ["yo" => $this->perfil($yo)->aArray(), "canales" => []];
        }

        $noLeidos = [];
        foreach ($this->em->createQuery(
            "SELECT IDENTITY(x.canal) AS canal, COUNT(x.id) AS n FROM App\Entity\ChatMensaje x
             JOIN App\Entity\ChatMiembro yo WITH yo.canal = x.canal AND yo.usuario = :yo
             WHERE x.id > yo.ultimoLeido AND (x.autor IS NULL OR x.autor != :yo) GROUP BY x.canal",
        )->setParameter("yo", $yo->getId())->getArrayResult() as $fila) {
            $noLeidos[(int) $fila["canal"]] = (int) $fila["n"];
        }

        $ultimos = [];
        /** @var ChatMensaje $m */
        foreach ($this->em->createQuery(
            "SELECT x, a FROM App\Entity\ChatMensaje x LEFT JOIN x.autor a WHERE x.id IN (
                SELECT MAX(y.id) FROM App\Entity\ChatMensaje y JOIN App\Entity\ChatMiembro ym WITH ym.canal = y.canal AND ym.usuario = :yo GROUP BY y.canal)",
        )->setParameter("yo", $yo->getId())->getResult() as $m) {
            $ultimos[(int) $m->getCanal()->getId()] = $m;
        }

        return [
            "yo" => $this->perfil($yo)->aArray(),
            "canales" => array_map(fn(ChatCanal $c) => $this->canalArray(
                $c,
                $yo,
                $noLeidos[$c->getId()] ?? 0,
                $ultimos[$c->getId()] ?? null,
            ), $canales),
        ];
    }

    public function canal(int $id, Usuario $yo): ChatCanal
    {
        $canal = $this->em->find(ChatCanal::class, $id);
        if ($canal === null || $canal->miembro($yo) === null) {
            throw new ChatRechazado("Conversación no encontrada.", 404);
        }

        return $canal;
    }

    /** @return array<string, mixed> el canal (existente o nuevo) */
    public function abrirDirecto(Usuario $yo, int $otroId): array
    {
        return $this->canalArray($this->directo($yo, $otroId), $yo);
    }

    /**
     * @param list<int> $miembros sin contar al creador
     *
     * @return array<string, mixed>
     */
    public function crearGrupo(Usuario $yo, string $nombre, array $miembros): array
    {
        $nombre = trim($nombre);
        if ($nombre === "" || mb_strlen($nombre) > self::MAX_NOMBRE) {
            throw new ChatRechazado(sprintf("El grupo necesita un nombre de hasta %d caracteres.", self::MAX_NOMBRE));
        }
        $usuarios = $this->usuarios($miembros, $yo);
        if ($usuarios === []) {
            throw new ChatRechazado("Agregue al menos una persona al grupo.");
        }
        $par = Directorio::parIncompatible(array_map($this->perfil(...), [$yo, ...$usuarios]));
        if ($par !== null) {
            throw new ChatRechazado(sprintf("%s y %s no pueden estar en el mismo grupo.", $par[0]->nombre, $par[1]->nombre));
        }
        $canal = ChatCanal::grupo($nombre, $yo, $usuarios);
        $this->em->persist($canal);
        $this->em->flush();
        $this->avisos->avisar(array_map(static fn(Usuario $u) => (int) $u->getId(), $usuarios), "canal", ["canal" => $canal->getId()]);

        return $this->canalArray($canal, $yo);
    }

    /**
     * Página de mensajes en orden cronológico: los últimos, los anteriores a
     * `antes` (scroll hacia atrás) o los posteriores a `despues` (aviso nuevo).
     *
     * @return list<array<string, mixed>>
     */
    public function mensajes(ChatCanal $canal, ?int $antes = null, ?int $despues = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("x", "a", "r", "ra")->from(ChatMensaje::class, "x")->leftJoin("x.autor", "a")
            ->leftJoin("x.respuestaA", "r")->leftJoin("r.autor", "ra")
            ->where("x.canal = :canal")->setParameter("canal", $canal)
            ->setMaxResults(self::PAGINA);
        if ($despues !== null) {
            $qb->andWhere("x.id > :despues")->setParameter("despues", $despues)->orderBy("x.id", "ASC");
        } else {
            $qb->orderBy("x.id", "DESC");
            if ($antes !== null) {
                $qb->andWhere("x.id < :antes")->setParameter("antes", $antes);
            }
        }
        /** @var list<ChatMensaje> $mensajes */
        $mensajes = $qb->getQuery()->getResult();
        if ($despues === null) {
            $mensajes = array_reverse($mensajes);
        }

        return $this->mensajesArray($mensajes);
    }

    /**
     * @param mixed     $adjuntos  lista `{tipo, id}` tal como llega
     * @param list<int> $archivos  ids subidos antes por `$yo` (`Archivos::subir`)
     *
     * @return array<string, mixed> el mensaje, ya con sus tarjetas
     */
    public function escribir(ChatCanal $canal, Usuario $yo, string $texto, mixed $adjuntos = [], array $archivos = [], ?int $respuestaA = null): array
    {
        $respuesta = null;
        if ($respuestaA !== null) {
            $respuesta = $this->em->find(ChatMensaje::class, $respuestaA);
            if ($respuesta === null || $respuesta->getCanal()->getId() !== $canal->getId()) {
                throw new ChatRechazado("El mensaje que responde no está en esta conversación.");
            }
        }
        $mensaje = $this->nuevo($canal, $yo, $texto, $this->tarjetas->normalizar($adjuntos), $this->archivos->sueltosDe($archivos, $yo), $respuesta);
        $this->em->flush();
        $this->avisarMensaje($mensaje);

        return $this->mensajesArray([$mensaje])[0];
    }

    /**
     * Envía el mismo mensaje (típicamente registros seleccionados en un
     * listado) a varias personas y/o grupos. Con un usuario se usa (o crea)
     * la conversación directa.
     *
     * @param list<int> $usuarios
     * @param list<int> $canales
     *
     * @return list<int> ids de los canales donde quedó el mensaje
     */
    public function compartir(Usuario $yo, array $usuarios, array $canales, string $texto, mixed $adjuntos): array
    {
        $adjuntos = $this->tarjetas->normalizar($adjuntos);
        $destinos = [];
        foreach (array_unique($canales) as $id) {
            $destinos[$id] = $this->canal((int) $id, $yo);
        }
        foreach (array_unique($usuarios) as $id) {
            $c = $this->directo($yo, (int) $id);
            $destinos[$c->getId()] = $c;
        }
        if ($destinos === []) {
            throw new ChatRechazado("Elija al menos un destinatario.");
        }
        $mensajes = array_map(fn(ChatCanal $c) => $this->nuevo($c, $yo, $texto, $adjuntos), array_values($destinos));
        $this->em->flush();
        array_walk($mensajes, $this->avisarMensaje(...));

        return array_map(static fn(ChatCanal $c) => (int) $c->getId(), array_values($destinos));
    }

    public function marcarLeido(ChatCanal $canal, Usuario $yo, int $hasta): void
    {
        $ultimo = (int) $this->em->createQuery("SELECT MAX(x.id) FROM App\Entity\ChatMensaje x WHERE x.canal = :canal")
            ->setParameter("canal", $canal)->getSingleScalarResult();
        $miembro = $canal->miembro($yo);
        $antes = $miembro->getUltimoLeido();
        $miembro->leyoHasta(min($hasta, $ultimo));
        if ($miembro->getUltimoLeido() !== $antes) {
            $this->em->flush();
            // A todos: los demás lo usan para el "visto".
            $this->avisos->avisar($this->idsMiembros($canal), "leido", ["canal" => $canal->getId(), "usuario" => $yo->getId(), "hasta" => $miembro->getUltimoLeido()]);
        }
    }

    /**
     * Aviso automático (sin autor) en el canal "Avisos del sistema" de cada
     * usuario. Las tarjetas no se validan contra un autor: cada quien las ve
     * con sus permisos al leer.
     *
     * @param list<Usuario>                      $usuarios
     * @param list<array{tipo: string, id: int}> $adjuntos
     */
    public function avisarSistema(array $usuarios, string $texto, array $adjuntos = []): void
    {
        $mensajes = [];
        foreach ($usuarios as $u) {
            $canal = $this->em->getRepository(ChatCanal::class)->findOneBy(["clave" => ChatCanal::claveSistema((int) $u->getId())]);
            if ($canal === null) {
                $canal = ChatCanal::sistema($u);
                $this->em->persist($canal);
            }
            $m = new ChatMensaje($canal, null, $texto, $adjuntos);
            $this->em->persist($m);
            $canal->huboActividad($m->getCreadoEn());
            $mensajes[] = $m;
        }
        $this->em->flush();
        array_walk($mensajes, $this->avisarMensaje(...));
    }

    /**
     * @param list<array{tipo: string, id: int}> $adjuntos ya normalizados
     * @param list<ChatArchivo>                  $archivos ya verificados
     */
    private function nuevo(ChatCanal $canal, Usuario $yo, string $texto, array $adjuntos, array $archivos = [], ?ChatMensaje $respuesta = null): ChatMensaje
    {
        $texto = trim($texto);
        if ($canal->getTipo() === TipoCanalChat::Sistema) {
            throw new ChatRechazado("En los avisos del sistema no se puede escribir.", 403);
        }
        if ($texto === "" && $adjuntos === [] && $archivos === []) {
            throw new ChatRechazado("El mensaje está vacío.");
        }
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            throw new ChatRechazado(sprintf("El mensaje supera los %d caracteres.", self::MAX_TEXTO));
        }
        if ($canal->getTipo() === TipoCanalChat::Directo) {
            $this->exigirDirectorio($yo, $this->otro($canal, $yo));
        }
        $mensaje = new ChatMensaje($canal, $yo, $texto, $adjuntos, $archivos, $respuesta);
        $this->em->persist($mensaje);
        $canal->huboActividad($mensaje->getCreadoEn());

        return $mensaje;
    }

    private function avisarMensaje(ChatMensaje $m): void
    {
        $autor = $m->getAutor();
        if ($autor !== null) {
            $m->getCanal()->miembro($autor)?->leyoHasta((int) $m->getId());
            $this->em->flush();
        }
        $this->avisos->avisar(
            $this->idsMiembros($m->getCanal()),
            "mensaje",
            [
                "canal" => $m->getCanal()->getId(),
                "mensaje" => $m->getId(),
                "autor" => $autor ? $this->perfil($autor)->aArray() : null,
                "extracto" => self::extracto($m),
            ],
        );
    }

    /** @return list<int> */
    private function idsMiembros(ChatCanal $canal): array
    {
        return array_values(array_map(static fn(ChatMiembro $x) => (int) $x->getUsuario()->getId(), $canal->getMiembros()->toArray()));
    }

    private function directo(Usuario $yo, int $otroId): ChatCanal
    {
        $otro = $this->em->find(Usuario::class, $otroId) ?? throw new ChatRechazado("Usuario no encontrado.", 404);
        $this->exigirDirectorio($yo, $otro);
        $canal = $this->em->getRepository(ChatCanal::class)->findOneBy(["clave" => ChatCanal::claveDirecto((int) $yo->getId(), $otroId)]);
        if ($canal === null) {
            $canal = ChatCanal::directo($yo, $otro);
            $this->em->persist($canal);
            $this->em->flush();
        }

        return $canal;
    }

    private function exigirDirectorio(Usuario $yo, ?Usuario $otro): void
    {
        if ($otro === null || !Directorio::puedenConversar($this->perfil($yo), $this->perfil($otro))) {
            throw new ChatRechazado("No puede conversar con este usuario.", 403);
        }
    }

    private function otro(ChatCanal $canal, Usuario $yo): ?Usuario
    {
        foreach ($canal->getMiembros() as $m) {
            if ($m->getUsuario()->getId() !== $yo->getId()) {
                return $m->getUsuario();
            }
        }

        return null;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Usuario>
     */
    private function usuarios(array $ids, Usuario $yo): array
    {
        $ids = array_values(array_diff(array_unique(array_map("intval", $ids)), [(int) $yo->getId()]));
        $usuarios = $ids === [] ? [] : $this->em->getRepository(Usuario::class)->findBy(["id" => $ids]);
        if (count($usuarios) !== count($ids)) {
            throw new ChatRechazado("Algún usuario no existe.", 404);
        }

        return $usuarios;
    }

    /** @return array<string, mixed> */
    private function canalArray(ChatCanal $c, Usuario $yo, int $noLeidos = 0, ?ChatMensaje $ultimo = null): array
    {
        $miembros = array_map(fn(ChatMiembro $m) => $this->perfil($m->getUsuario())->aArray(), $c->getMiembros()->toArray());
        $otros = array_values(array_filter($miembros, static fn(array $p) => $p["id"] !== $yo->getId()));
        $directo = $c->getTipo() === TipoCanalChat::Directo;

        return [
            "id" => $c->getId(),
            "tipo" => $c->getTipo()->value,
            "nombre" => $directo ? ($otros[0]["nombre"] ?? "—") : $c->getNombre(),
            "contacto" => $directo ? ($otros[0] ?? null) : null,
            "miembros" => array_values($miembros),
            "noLeidos" => $noLeidos,
            "leidoHasta" => $c->miembro($yo)?->getUltimoLeido() ?? 0,
            // Hasta dónde leyó cada uno de los demás: el "visto" de mis mensajes.
            "lecturas" => array_values(array_map(static fn(ChatMiembro $m) => ["usuario" => $m->getUsuario()->getId(), "hasta" => $m->getUltimoLeido()], array_filter(
                $c->getMiembros()->toArray(),
                static fn(ChatMiembro $m) => $m->getUsuario()->getId() !== $yo->getId(),
            ))),
            "actividad" => $c->getActividad()->format(DATE_ATOM),
            "ultimo" => $ultimo ? [
                "id" => $ultimo->getId(),
                "autor" => $ultimo->getAutor()?->getId(),
                "extracto" => self::extracto($ultimo),
                "fecha" => $ultimo->getCreadoEn()->format(DATE_ATOM),
            ] : null,
        ];
    }

    /**
     * @param list<ChatMensaje> $mensajes
     *
     * @return list<array<string, mixed>>
     */
    private function mensajesArray(array $mensajes): array
    {
        if ($mensajes !== []) {
            // Carga los archivos de toda la página en una consulta (evita N+1).
            $this->em->createQuery("SELECT x, f FROM App\Entity\ChatMensaje x LEFT JOIN x.archivos f WHERE x IN (:m)")
                ->setParameter("m", $mensajes)->getResult();
        }
        $tarjetas = $this->tarjetas->presentarVarios(array_map(static fn(ChatMensaje $m) => $m->getAdjuntos(), $mensajes));

        return array_map(fn(ChatMensaje $m, int $i) => [
            "id" => $m->getId(),
            "canal" => $m->getCanal()->getId(),
            "autor" => $m->getAutor() ? $this->perfil($m->getAutor())->aArray() : null,
            "texto" => $m->getTexto(),
            "fecha" => $m->getCreadoEn()->format(DATE_ATOM),
            "adjuntos" => $tarjetas[$i],
            "archivos" => array_map($this->archivos->presentar(...), $m->getArchivos()->toArray()),
            "respuesta" => ($r = $m->getRespuestaA()) ? [
                "id" => $r->getId(),
                "autor" => $r->getAutor() ? $this->perfil($r->getAutor())->nombre : "Sistema",
                "extracto" => self::extracto($r),
            ] : null,
        ], $mensajes, array_keys($mensajes));
    }

    /** Vista previa en la bandeja y en el aviso: el texto, o qué se compartió. */
    public static function extracto(ChatMensaje $m): string
    {
        $texto = preg_replace('/\s+/', " ", trim($m->getTexto())) ?? "";
        if ($texto !== "") {
            return mb_strlen($texto) > 140 ? mb_substr($texto, 0, 139) . "…" : $texto;
        }
        $archivos = $m->getArchivos();
        if ($archivos->count() > 0) {
            $fotos = $archivos->filter(static fn(ChatArchivo $a) => $a->esImagen())->count();
            if ($fotos === $archivos->count()) {
                return $fotos === 1 ? "Foto" : sprintf("%d fotos", $fotos);
            }

            return $archivos->count() === 1 ? sprintf("Archivo: %s", $archivos->first()->getNombre()) : sprintf("%d archivos", $archivos->count());
        }
        $n = count($m->getAdjuntos());

        return $n === 1 ? sprintf("Compartió %s %d", $m->getAdjuntos()[0]["tipo"], $m->getAdjuntos()[0]["id"]) : sprintf("Compartió %d registros", $n);
    }
}
