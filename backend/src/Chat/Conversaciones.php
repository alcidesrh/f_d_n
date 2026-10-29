<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Push\AvisosPush;
use App\Chat\Tarjeta\Tarjetas;
use App\Cuenta\FotoPerfil;
use App\Entity\ChatArchivo;
use App\Entity\ChatCanal;
use App\Entity\ChatMensaje;
use App\Entity\ChatMiembro;
use App\Entity\ChatSubgrupo;
use App\Entity\Enum\ModoGrupoChat;
use App\Entity\Enum\TipoCanalChat;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Casos de uso del chat interno: directorio de contactos, bandeja, abrir
 * conversaciones, crear y editar grupos, leer, escribir, editar, reenviar y
 * eliminar mensajes y compartir registros. Todo se mira desde el usuario de
 * la sesión (`$yo`): solo ve los canales donde es miembro.
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
        private readonly FotoGrupo $fotosGrupo,
        private readonly AvisosPush $push,
    ) {}

    private function perfil(Usuario $u): Perfil
    {
        return Perfil::de($u, $this->fotos);
    }

    /**
     * De `$ids`, los usuarios con quienes `$yo` puede conversar (el mismo criterio que `contactos`).
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function conversables(Usuario $yo, array $ids): array
    {
        $ids = array_values(array_diff(array_unique($ids), [(int) $yo->getId()]));
        if ($ids === []) {
            return [];
        }
        $usuarios = $this->em->createQuery(
            "SELECT u, a, e FROM App\Entity\Usuario u LEFT JOIN u.agencia a LEFT JOIN u.estacion e WHERE u.id IN (:ids)",
        )->setParameter("ids", $ids)->getResult();
        $mio = $this->perfil($yo);

        return array_values(array_map(
            static fn(Perfil $p) => $p->id,
            array_filter(array_map($this->perfil(...), $usuarios), static fn(Perfil $p) => Directorio::puedenConversar($mio, $p)),
        ));
    }

    /** @return list<array<string, mixed>> con quién puede hablar `$yo`, por nombre */
    public function contactos(Usuario $yo): array
    {
        $usuarios = $this->em->createQuery(
            "SELECT u, a, e FROM App\Entity\Usuario u LEFT JOIN u.agencia a LEFT JOIN u.estacion e WHERE u.id != :yo",
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

        // Grupos miembros de cada canal y cuáles de mis canales son miembros de otro, en dos consultas.
        $this->em->createQuery("SELECT c, s, g FROM App\Entity\ChatCanal c LEFT JOIN c.subgrupos s LEFT JOIN s.grupo g WHERE c IN (:c)")
            ->setParameter("c", $canales)->getResult();
        $contenidos = array_flip(array_map("intval", array_column($this->em->createQuery(
            "SELECT DISTINCT IDENTITY(s.grupo) AS g FROM App\Entity\ChatSubgrupo s WHERE s.grupo IN (:c)",
        )->setParameter("c", $canales)->getScalarResult(), "g")));

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
                isset($contenidos[$c->getId()]),
            ), $canales),
        ];
    }

    /**
     * Cambia la foto del grupo por `$archivo`, o la quita si es `null`. Solo los grupos
     * la tienen; en uno abierto la cambia cualquiera de sus miembros, en uno
     * dirigido solo quien lo administra. Avisa a los demás.
     *
     * @return array<string, mixed> el canal
     */
    public function cambiarFotoGrupo(Usuario $yo, ChatCanal $canal, ?UploadedFile $archivo): array
    {
        if ($canal->getTipo() !== TipoCanalChat::Grupo) {
            throw new ChatRechazado("Solo los grupos tienen foto.");
        }
        if ($canal->getModo() === ModoGrupoChat::Dirigido && !$canal->administra($yo)) {
            throw new ChatRechazado("En un grupo dirigido solo el dueño cambia la foto.", 403);
        }
        try {
            if ($archivo === null) {
                $this->fotosGrupo->quitar($canal);
            } else {
                $canal->setFoto($this->fotosGrupo->guardar($archivo, $canal));
            }
        } catch (\DomainException $e) {
            throw new ChatRechazado($e->getMessage());
        }
        $this->em->flush();
        $this->avisos->avisar($this->idsMiembros($canal), "canal", ["canal" => $canal->getId()]);

        return $this->canalArray($canal, $yo);
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
    public function crearGrupo(Usuario $yo, string $nombre, array $miembros, string $modo = "abierto"): array
    {
        $nombre = self::nombreGrupo($nombre);
        $modo = self::modo($modo);
        $usuarios = $this->usuarios($miembros, $yo);
        if ($usuarios === []) {
            throw new ChatRechazado("Agregue al menos una persona al grupo.");
        }
        $par = Directorio::parIncompatible(array_map($this->perfil(...), [$yo, ...$usuarios]));
        if ($par !== null) {
            throw new ChatRechazado(sprintf("%s y %s no pueden estar en el mismo grupo.", $par[0]->nombre, $par[1]->nombre));
        }
        $canal = ChatCanal::grupo($nombre, $yo, $usuarios, $modo);
        $this->em->persist($canal);
        $this->em->flush();
        $this->avisos->avisar(array_map(static fn(Usuario $u) => (int) $u->getId(), $usuarios), "canal", ["canal" => $canal->getId()]);

        return $this->canalArray($canal, $yo);
    }

    /**
     * Edita un grupo de una vez (lo que muestra el diálogo): nombre, modo,
     * miembros por sí mismos, emisores y grupos miembros. Solo quien lo
     * administra; él y el dueño siguen siendo miembros. Los cambios llegan a
     * los grupos que lo tienen como miembro. Avisa a todos los que estaban y
     * a los que entran (`canal`).
     *
     * @param list<int>                         $miembros  usuarios por sí mismos
     * @param list<int>                         $emisores  usuarios que escriben en un dirigido
     * @param list<array{id: int, emisor: bool}> $subgrupos grupos miembros
     *
     * @return array<string, mixed>
     */
    public function editarGrupo(Usuario $yo, ChatCanal $canal, string $nombre, string $modo, array $miembros, array $emisores, array $subgrupos): array
    {
        if (!$canal->administra($yo)) {
            throw new ChatRechazado("Solo el dueño del grupo puede editarlo.", 403);
        }
        $canal->renombrar(self::nombreGrupo($nombre));
        $modo = self::modo($modo);
        $esMiembro = $this->contenedores($canal) !== [];
        if ($modo === ModoGrupoChat::Dirigido && $esMiembro) {
            throw new ChatRechazado("Este grupo es miembro de otro grupo: solo puede ser abierto.");
        }
        $canal->setModo($modo);
        $antes = $this->idsMiembros($canal);
        $leido = $this->ultimoId($canal);

        // Miembros por sí mismos: los elegidos, quien edita y el dueño.
        $quedan = [...$this->usuarios($miembros, $yo), $yo];
        if ($canal->getCreadoPor() !== null) {
            $quedan[] = $canal->getCreadoPor();
        }
        $ids = array_map(static fn(Usuario $u) => (int) $u->getId(), $quedan);
        foreach ($canal->getMiembros()->toArray() as $m) {
            if ($m->esDirecto() && !in_array((int) $m->getUsuario()->getId(), $ids, true)) {
                $canal->quitar($m->getUsuario());
            }
        }
        foreach ($quedan as $u) {
            $canal->agregar($u, $leido);
        }
        foreach ($canal->getMiembros() as $m) {
            $m->setEmisor(in_array((int) $m->getUsuario()->getId(), $emisores, true));
        }

        // Grupos miembros.
        $elegidos = [];
        foreach ($subgrupos as $s) {
            $elegidos[(int) $s["id"]] = (bool) $s["emisor"];
        }
        foreach ($canal->getSubgrupos()->toArray() as $s) {
            if (!isset($elegidos[(int) $s->getGrupo()->getId()])) {
                $canal->quitarSubgrupo($s->getGrupo());
            }
        }
        foreach ($elegidos as $id => $emisor) {
            $grupo = $this->em->find(ChatCanal::class, $id);
            if ($grupo === null || $canal->subgrupo($grupo) === null) {
                $grupo = $this->exigirSubgrupo($yo, $canal, $grupo, $esMiembro);
            }
            $canal->agregarSubgrupo($grupo, $emisor);
        }
        $canal->sincronizar($leido);
        $this->exigirCompatibles($canal);
        $afectados = $this->propagar($canal);
        $this->em->flush();

        $this->avisos->avisar(array_values(array_unique([...$antes, ...$this->idsMiembros($canal), ...$afectados])), "canal", ["canal" => $canal->getId()]);

        return $this->canalArray($canal, $yo, esSubgrupo: $esMiembro);
    }

    /**
     * `$yo` deja el grupo. El dueño no puede (lo administra). Si además está
     * por un grupo miembro, sigue recibiendo por ese grupo.
     *
     * @return bool si sigue en el grupo (por un grupo miembro)
     */
    public function salirDeGrupo(Usuario $yo, ChatCanal $canal): bool
    {
        if ($canal->getTipo() !== TipoCanalChat::Grupo) {
            throw new ChatRechazado("Solo se sale de los grupos.");
        }
        if ($canal->getCreadoPor()?->getId() === $yo->getId()) {
            throw new ChatRechazado("El dueño no puede salir del grupo.");
        }
        $antes = $this->idsMiembros($canal);
        $canal->quitar($yo);
        $afectados = $this->propagar($canal);
        $this->em->flush();
        $this->avisos->avisar(array_values(array_unique([...$antes, ...$afectados])), "canal", ["canal" => $canal->getId()]);

        return $canal->miembro($yo) !== null;
    }

    /**
     * Pone al día los grupos que tienen a `$grupo` como miembro, tras cambiar
     * sus miembros.
     *
     * @return list<int> los usuarios de esos grupos, antes y después (para avisarles)
     */
    private function propagar(ChatCanal $grupo): array
    {
        $ids = [];
        foreach ($this->contenedores($grupo) as $c) {
            $ids = [...$ids, ...$this->idsMiembros($c)];
            $c->sincronizar($this->ultimoId($c));
            $this->exigirCompatibles($c);
            $ids = [...$ids, ...$this->idsMiembros($c)];
        }

        return $ids;
    }

    /** @return list<ChatCanal> los grupos de los que `$grupo` es miembro */
    private function contenedores(ChatCanal $grupo): array
    {
        if ($grupo->getId() === null) {
            return [];
        }

        return array_map(static fn(ChatSubgrupo $s) => $s->getCanal(), $this->em->getRepository(ChatSubgrupo::class)->findBy(["grupo" => $grupo]));
    }

    /** Un grupo nuevo como miembro de `$canal`: abierto, de `$yo`, y sin pasar de un nivel. */
    private function exigirSubgrupo(Usuario $yo, ChatCanal $canal, ?ChatCanal $grupo, bool $canalEsMiembro): ChatCanal
    {
        if ($grupo === null || $grupo->getTipo() !== TipoCanalChat::Grupo || $grupo->miembro($yo) === null) {
            throw new ChatRechazado("Grupo no encontrado.", 404);
        }
        if ($grupo === $canal) {
            throw new ChatRechazado("Un grupo no puede ser miembro de sí mismo.");
        }
        if ($grupo->getModo() !== ModoGrupoChat::Abierto) {
            throw new ChatRechazado(sprintf("«%s» es dirigido: solo los grupos abiertos pueden ser miembros de otro.", $grupo->getNombre()));
        }
        if ($canalEsMiembro) {
            throw new ChatRechazado("Este grupo ya es miembro de otro grupo: no puede tener grupos como miembros.");
        }
        if ($grupo->getSubgrupos()->count() > 0) {
            throw new ChatRechazado(sprintf("«%s» tiene grupos como miembros: no puede ser miembro de otro.", $grupo->getNombre()));
        }

        return $grupo;
    }

    /** La regla del directorio, para cada par de miembros (también los que llegan por un grupo). */
    private function exigirCompatibles(ChatCanal $canal): void
    {
        $par = Directorio::parIncompatible(array_values(array_map(fn(ChatMiembro $m) => $this->perfil($m->getUsuario()), $canal->getMiembros()->toArray())));
        if ($par !== null) {
            throw new ChatRechazado(sprintf("%s y %s no pueden estar en el mismo grupo.", $par[0]->nombre, $par[1]->nombre));
        }
    }

    private function ultimoId(ChatCanal $canal): int
    {
        return $canal->getId() === null ? 0 : (int) $this->em->createQuery("SELECT MAX(x.id) FROM App\Entity\ChatMensaje x WHERE x.canal = :canal")
            ->setParameter("canal", $canal)->getSingleScalarResult();
    }

    private static function nombreGrupo(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === "" || mb_strlen($nombre) > self::MAX_NOMBRE) {
            throw new ChatRechazado(sprintf("El grupo necesita un nombre de hasta %d caracteres.", self::MAX_NOMBRE));
        }

        return $nombre;
    }

    private static function modo(string $modo): ModoGrupoChat
    {
        return ModoGrupoChat::tryFrom($modo) ?? throw new ChatRechazado("El grupo es abierto o dirigido.");
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
            ->select("x", "a", "r", "ra", "v")->from(ChatMensaje::class, "x")->leftJoin("x.autor", "a")
            ->leftJoin("x.respuestaA", "r")->leftJoin("r.autor", "ra")->leftJoin("x.via", "v")
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
     * Con `$edita`, el mensaje corrige uno propio: queda como respuesta a él,
     * marcado "editado" (el original no cambia). `$conservar`: archivos del
     * original que siguen en la versión editada.
     *
     * @param mixed     $adjuntos  lista `{tipo, id}` tal como llega
     * @param list<int> $archivos  ids subidos antes por `$yo` (`Archivos::subir`)
     * @param list<int> $conservar ids de archivos de `$edita`
     *
     * @return array<string, mixed> el mensaje, ya con sus tarjetas
     */
    public function escribir(ChatCanal $canal, Usuario $yo, string $texto, mixed $adjuntos = [], array $archivos = [], ?int $respuestaA = null, ?int $edita = null, array $conservar = []): array
    {
        $respuesta = null;
        if ($respuestaA !== null && $edita === null) {
            $respuesta = $this->em->find(ChatMensaje::class, $respuestaA);
            if ($respuesta === null || $respuesta->getCanal()->getId() !== $canal->getId()) {
                throw new ChatRechazado("El mensaje que responde no está en esta conversación.");
            }
        }
        $archivos = $this->archivos->sueltosDe($archivos, $yo);
        $original = null;
        if ($edita !== null) {
            $original = $this->em->find(ChatMensaje::class, $edita);
            if ($original === null || $original->getCanal()->getId() !== $canal->getId() || $original->estaEliminado()) {
                throw new ChatRechazado("El mensaje que edita no está en esta conversación.");
            }
            if ($original->getAutor()?->getId() !== $yo->getId()) {
                throw new ChatRechazado("Solo puede editar sus propios mensajes.", 403);
            }
            foreach ($original->getArchivos() as $a) {
                if (in_array((int) $a->getId(), $conservar, true)) {
                    $archivos[] = $copia = $a->copiar($yo);
                    $this->em->persist($copia);
                }
            }
        }
        $mensaje = $this->nuevo($canal, $yo, $texto, $this->tarjetas->normalizar($adjuntos), $archivos, $respuesta);
        if ($original !== null) {
            $mensaje->comoEdicionDe($original);
        }
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
        $destinos = $this->destinos($yo, $usuarios, $canales);
        $mensajes = array_map(fn(ChatCanal $c) => $this->nuevo($c, $yo, $texto, $adjuntos), $destinos);
        $this->em->flush();
        array_walk($mensajes, $this->avisarMensaje(...));

        return array_map(static fn(ChatCanal $c) => (int) $c->getId(), $destinos);
    }

    /**
     * Reenvía un mensaje de una conversación de `$yo` a personas y/o grupos:
     * el mismo texto, los mismos archivos (copiados) y los registros que
     * `$yo` puede ver, marcado "reenviado".
     *
     * @param list<int> $usuarios
     * @param list<int> $canales
     *
     * @return list<int> ids de los canales donde quedó
     */
    public function reenviar(Usuario $yo, int $mensajeId, array $usuarios, array $canales): array
    {
        $original = $this->mensajeVisible($yo, $mensajeId);
        $adjuntos = array_values(array_map(
            static fn(array $t) => ["tipo" => $t["tipo"], "id" => $t["id"]],
            array_filter($this->tarjetas->presentar($original->getAdjuntos()), static fn(array $t) => $t["estado"] === "ok"),
        ));
        $destinos = $this->destinos($yo, $usuarios, $canales);
        $mensajes = [];
        foreach ($destinos as $c) {
            $copias = array_map(fn(ChatArchivo $a) => $a->copiar($yo), $original->getArchivos()->toArray());
            array_walk($copias, $this->em->persist(...));
            $m = $this->nuevo($c, $yo, $original->getTexto(), $adjuntos, $copias);
            $m->comoReenvio();
            $mensajes[] = $m;
        }
        $this->em->flush();
        array_walk($mensajes, $this->avisarMensaje(...));

        return array_map(static fn(ChatCanal $c) => (int) $c->getId(), $destinos);
    }

    /**
     * Elimina un mensaje para todos: queda "Se eliminó este mensaje" (las
     * respuestas siguen apuntando a él). Puede su autor y, en un grupo, quien
     * lo administra. Avisa `cambio` a los miembros.
     *
     * @return array<string, mixed> el mensaje ya eliminado
     */
    public function eliminarMensaje(Usuario $yo, int $mensajeId): array
    {
        $mensaje = $this->mensajeVisible($yo, $mensajeId);
        if ($mensaje->getAutor()?->getId() !== $yo->getId() && !$mensaje->getCanal()->administra($yo)) {
            throw new ChatRechazado("Solo puede eliminar sus propios mensajes.", 403);
        }
        $this->archivos->borrar($mensaje->getArchivos()->toArray());
        $mensaje->getArchivos()->clear();
        $mensaje->eliminar();
        $this->em->flush();
        $this->avisos->avisar($this->idsMiembros($mensaje->getCanal()), "cambio", ["canal" => $mensaje->getCanal()->getId(), "mensaje" => $mensaje->getId()]);

        return $this->mensajesArray([$mensaje])[0];
    }

    /** Un mensaje no eliminado de una conversación de `$yo`. */
    private function mensajeVisible(Usuario $yo, int $id): ChatMensaje
    {
        $mensaje = $this->em->find(ChatMensaje::class, $id);
        if ($mensaje === null || $mensaje->getCanal()->miembro($yo) === null || $mensaje->estaEliminado()) {
            throw new ChatRechazado("Mensaje no encontrado.", 404);
        }

        return $mensaje;
    }

    /**
     * Las conversaciones de destino: los grupos (o canales) elegidos y la
     * directa con cada persona (la crea si no existe).
     *
     * @param list<int> $usuarios
     * @param list<int> $canales
     *
     * @return list<ChatCanal>
     */
    private function destinos(Usuario $yo, array $usuarios, array $canales): array
    {
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

        return array_values($destinos);
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
     * Los archivos (generados, sin autor: `Archivos::guardarGenerado`) van
     * en el mensaje del primero; a los demás, una copia del registro.
     *
     * @param list<Usuario>                      $usuarios
     * @param list<array{tipo: string, id: int}> $adjuntos
     * @param list<ChatArchivo>                  $archivos
     */
    public function avisarSistema(array $usuarios, string $texto, array $adjuntos = [], array $archivos = []): void
    {
        $mensajes = [];
        foreach (array_values($usuarios) as $i => $u) {
            $canal = $this->em->getRepository(ChatCanal::class)->findOneBy(["clave" => ChatCanal::claveSistema((int) $u->getId())]);
            if ($canal === null) {
                $canal = ChatCanal::sistema($u);
                $this->em->persist($canal);
            }
            $propios = $i === 0 ? $archivos : array_map(function (ChatArchivo $a): ChatArchivo {
                $copia = $a->copiar(null);
                $this->em->persist($copia);

                return $copia;
            }, $archivos);
            $m = new ChatMensaje($canal, null, $texto, $adjuntos, $propios);
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
        } elseif (!$canal->puedeEscribir($yo)) {
            throw new ChatRechazado(sprintf("En «%s» solo escriben el dueño y quienes él elija.", $canal->getNombre()), 403);
        }
        $mensaje = new ChatMensaje($canal, $yo, $texto, $adjuntos, $archivos, $respuesta);
        $mensaje->desde($canal->miembro($yo)?->getVia());
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
        $miembros = $this->idsMiembros($m->getCanal());
        $perfil = $autor ? $this->perfil($autor) : null;
        $extracto = self::extracto($m);
        $this->avisos->avisar(
            $miembros,
            "mensaje",
            [
                "canal" => $m->getCanal()->getId(),
                "mensaje" => $m->getId(),
                "autor" => $perfil?->aArray(),
                "extracto" => $extracto,
            ],
        );
        // Con la app cerrada: a todos menos al autor (el navegador la calla si la app está a la vista).
        $canal = $m->getCanal();
        $this->push->avisarMensaje(
            array_values(array_filter($miembros, static fn(int $id) => $id !== $perfil?->id)),
            (int) $canal->getId(),
            $canal->getTipo(),
            $canal->getNombre(),
            $perfil?->nombre,
            $extracto,
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

    /**
     * @param bool|null $esSubgrupo el canal es miembro de otro grupo (null: se consulta)
     *
     * @return array<string, mixed>
     */
    private function canalArray(ChatCanal $c, Usuario $yo, int $noLeidos = 0, ?ChatMensaje $ultimo = null, ?bool $esSubgrupo = null): array
    {
        $miembros = array_map(fn(ChatMiembro $m) => [
            ...$this->perfil($m->getUsuario())->aArray(),
            "via" => $m->getVia()?->getId(),
            "emisor" => $m->esEmisor(),
        ], $c->getMiembros()->toArray());
        $otros = array_values(array_filter($miembros, static fn(array $p) => $p["id"] !== $yo->getId()));
        $directo = $c->getTipo() === TipoCanalChat::Directo;
        $grupo = $c->getTipo() === TipoCanalChat::Grupo;

        return [
            "id" => $c->getId(),
            "tipo" => $c->getTipo()->value,
            "nombre" => $directo ? ($otros[0]["nombre"] ?? "—") : $c->getNombre(),
            "contacto" => $directo ? ($otros[0] ?? null) : null,
            "foto" => $directo ? null : $this->fotosGrupo->url($c),
            "miembros" => array_values($miembros),
            "modo" => $c->getModo()?->value,
            "dueno" => $grupo ? $c->getCreadoPor()?->getId() : null,
            "administro" => $c->administra($yo),
            "puedeEscribir" => $c->puedeEscribir($yo),
            "subgrupos" => array_values(array_map(fn(ChatSubgrupo $s) => [
                "id" => $s->getGrupo()->getId(),
                "nombre" => $s->getGrupo()->getNombre(),
                "foto" => $this->fotosGrupo->url($s->getGrupo()),
                "emisor" => $s->esEmisor(),
            ], $c->getSubgrupos()->toArray())),
            "esSubgrupo" => $grupo && ($esSubgrupo ?? $this->contenedores($c) !== []),
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
            "via" => ($v = $m->getVia()) ? ["id" => $v->getId(), "nombre" => $v->getNombre()] : null,
            "editado" => $m->getEditaA() !== null,
            "reenviado" => $m->esReenvio(),
            "eliminado" => $m->estaEliminado(),
        ], $mensajes, array_keys($mensajes));
    }

    /** Vista previa en la bandeja y en el aviso: el texto, o qué se compartió. */
    public static function extracto(ChatMensaje $m): string
    {
        if ($m->estaEliminado()) {
            return "Se eliminó este mensaje";
        }
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
