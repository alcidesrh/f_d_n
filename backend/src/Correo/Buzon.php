<?php

declare(strict_types=1);

namespace App\Correo;

use App\Chat\AvisosChat;
use App\Entity\CorreoAdjunto;
use App\Entity\CorreoCuenta;
use App\Entity\CorreoMensaje;
use App\Entity\Enum\CarpetaCorreo;
use Doctrine\ORM\EntityManagerInterface;

/**
 * El buzón de una cuenta: listar con filtros, leer, marcar (leído,
 * destacado), mover a la papelera, restaurar y eliminar. Cada operación
 * solo toca correos de la propia cuenta. Los cambios se avisan a las otras
 * pestañas del usuario por el tópico privado del chat (aviso `correo`).
 */
final class Buzon
{
    public const ACCIONES = ["leido", "noLeido", "destacar", "quitarDestacado", "papelera", "restaurar", "eliminar"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Cuentas $cuentas,
        private readonly Adjuntos $adjuntos,
        private readonly AvisosChat $avisos,
    ) {}

    /** @return array{correos: list<array<string, mixed>>, mas: bool} */
    public function listar(CorreoCuenta $cuenta, Filtro $f): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("m")->from(CorreoMensaje::class, "m")
            ->where("m.cuenta = :cuenta")->setParameter("cuenta", $cuenta)
            ->orderBy("m.fecha", "DESC")->addOrderBy("m.id", "DESC")
            ->setMaxResults(Filtro::POR_PAGINA + 1);
        if ($f->esDestacados()) {
            $qb->andWhere("m.destacado = true")->andWhere("m.carpeta IN (:vivas)")->setParameter("vivas", [CarpetaCorreo::Entrada->value, CarpetaCorreo::Enviados->value]);
        } else {
            $qb->andWhere("m.carpeta = :carpeta")->setParameter("carpeta", $f->carpeta->value);
        }
        if ($f->noLeidos) {
            $qb->andWhere("m.leido = false");
        }
        if ($f->conAdjuntos) {
            $qb->andWhere(sprintf("EXISTS (SELECT a.id FROM %s a WHERE a.mensaje = m AND a.enLinea = false)", CorreoAdjunto::class));
        }
        if ($f->origen !== null) {
            $qb->andWhere("m.externo = :externo")->setParameter("externo", $f->origen === Filtro::EXTERNOS);
        }
        foreach ($f->palabras() as $i => $palabra) {
            $qb->andWhere("(LOWER(m.asunto) LIKE :p{$i} OR LOWER(m.deDireccion) LIKE :p{$i} OR LOWER(COALESCE(m.deNombre, '')) LIKE :p{$i} OR LOWER(m.texto) LIKE :p{$i} OR LOWER(CAST(m.para AS text)) LIKE :p{$i})")
                ->setParameter("p{$i}", "%" . addcslashes($palabra, "%_\\") . "%");
        }
        if ($f->antes !== null) {
            $ancla = $this->em->find(CorreoMensaje::class, $f->antes);
            if ($ancla !== null && $ancla->getCuenta() === $cuenta) {
                $qb->andWhere("m.fecha < :fa OR (m.fecha = :fa AND m.id < :ia)")->setParameter("fa", $ancla->getFecha())->setParameter("ia", $f->antes);
            }
        }
        /** @var list<CorreoMensaje> $filas */
        $filas = $qb->getQuery()->getResult();
        $mas = count($filas) > Filtro::POR_PAGINA;
        $filas = array_slice($filas, 0, Filtro::POR_PAGINA);
        $conAdjuntos = $this->conAdjuntos(array_map(static fn(CorreoMensaje $m) => (int) $m->getId(), $filas));

        return ["correos" => array_map(fn(CorreoMensaje $m) => $this->resumen($m, isset($conAdjuntos[(int) $m->getId()])), $filas), "mas" => $mas];
    }

    /** La cuenta y los contadores de cada vista. @return array<string, mixed> */
    public function estado(CorreoCuenta $cuenta, bool $puedeExterno): array
    {
        $filas = $this->em->getConnection()->fetchAllAssociative(
            "SELECT carpeta, COUNT(*) AS total, COUNT(*) FILTER (WHERE NOT leido) AS no_leidos, COUNT(*) FILTER (WHERE destacado) AS destacados FROM correo_mensaje WHERE cuenta_id = ? GROUP BY carpeta",
            [$cuenta->getId()],
        );
        $por = [];
        foreach ($filas as $f) {
            $por[$f["carpeta"]] = $f;
        }
        $u = $cuenta->getUsuario();
        $nombre = trim(($u->getNombre() ?? "") . " " . ($u->getApellido() ?? ""));

        return [
            "direccion" => $this->cuentas->direccion($cuenta),
            "nombre" => $nombre !== "" ? $nombre : $u->getUsername(),
            "externo" => $puedeExterno,
            "carpetas" => [
                "entrada" => ["noLeidos" => (int) ($por["entrada"]["no_leidos"] ?? 0)],
                "destacados" => ["total" => (int) ($por["entrada"]["destacados"] ?? 0) + (int) ($por["enviados"]["destacados"] ?? 0)],
                "enviados" => ["fallidos" => $this->fallidos($cuenta)],
                "borradores" => ["total" => (int) ($por["borradores"]["total"] ?? 0)],
                "papelera" => ["total" => (int) ($por["papelera"]["total"] ?? 0)],
            ],
        ];
    }

    public function correo(CorreoCuenta $cuenta, int $id): CorreoMensaje
    {
        $m = $this->em->find(CorreoMensaje::class, $id);
        if ($m === null || $m->getCuenta() !== $cuenta) {
            throw new CorreoRechazado("El correo no existe.", 404);
        }

        return $m;
    }

    /** El correo completo para leerlo (o reabrir el borrador). @return array<string, mixed> */
    public function detalle(CorreoMensaje $m): array
    {
        $visibles = [];
        $incrustadas = [];
        foreach ($m->getAdjuntos() as $a) {
            if ($a->isEnLinea() && $a->getContentId() !== null) {
                $incrustadas[$a->getContentId()] = $a;
            } else {
                $visibles[] = $this->adjuntos->presentar($a);
            }
        }
        $html = $m->getHtml();
        if ($html !== null && $incrustadas !== []) {
            // `cid:` → la URL firmada (la API la sirve; el navegador antepone su base).
            $html = preg_replace_callback('~cid:([^"\'\s)>]+)~i', function (array $c) use ($incrustadas) {
                $a = $incrustadas[rawurldecode($c[1])] ?? null;

                return $a !== null ? "{api}" . $this->adjuntos->url($a) : $c[0];
            }, $html) ?? $html;
        }
        $propio = $m->getCarpeta() === CarpetaCorreo::Enviados || $m->esBorrador() || ($m->getCarpetaPrevia() === CarpetaCorreo::Enviados);

        return [
            ...$this->resumen($m, $visibles !== []),
            "cc" => $m->getCc(),
            "cco" => $propio ? $m->getCco() : [],
            "responderA" => $m->getResponderA(),
            "texto" => $m->getTexto(),
            "html" => $html,
            "errorEnvio" => $m->getErrorEnvio(),
            "adjuntos" => $visibles,
            "origen" => $m->getOrigen()?->getId(),
        ];
    }

    /**
     * La misma acción sobre varios correos de la cuenta.
     *
     * @param list<int> $ids
     */
    public function aplicar(CorreoCuenta $cuenta, array $ids, string $accion): int
    {
        if (!in_array($accion, self::ACCIONES, true)) {
            throw new CorreoRechazado("Acción desconocida.");
        }
        $ids = array_slice(array_values(array_unique(array_filter($ids, static fn(int $id) => $id > 0))), 0, 500);
        if ($ids === []) {
            return 0;
        }
        /** @var list<CorreoMensaje> $correos */
        $correos = $this->em->getRepository(CorreoMensaje::class)->findBy(["id" => $ids, "cuenta" => $cuenta]);
        $rutas = [];
        foreach ($correos as $m) {
            match ($accion) {
                "leido" => $m->setLeido(true),
                "noLeido" => $m->setLeido(false),
                "destacar" => $m->setDestacado(true),
                "quitarDestacado" => $m->setDestacado(false),
                // Un borrador a la papelera es descartarlo: no se restaura.
                "papelera" => $m->esBorrador() ? $this->quitar($m, $rutas) : $m->aLaPapelera(),
                "restaurar" => $m->restaurar(),
                // Eliminar para siempre solo desde la papelera (o un borrador).
                "eliminar" => $m->getCarpeta() === CarpetaCorreo::Papelera || $m->esBorrador() ? $this->quitar($m, $rutas) : $m->aLaPapelera(),
            };
        }
        $this->em->flush();
        $this->adjuntos->purgar($rutas);
        $this->avisar($cuenta);

        return count($correos);
    }

    public function vaciarPapelera(CorreoCuenta $cuenta): int
    {
        $ids = array_map("intval", array_column($this->em->createQueryBuilder()->select("m.id")->from(CorreoMensaje::class, "m")->where("m.cuenta = :c")->andWhere("m.carpeta = :p")->setParameter("c", $cuenta)->setParameter("p", CarpetaCorreo::Papelera->value)->getQuery()->getScalarResult(), "id"));

        return $this->aplicar($cuenta, $ids, "eliminar");
    }

    /**
     * Para el campo de destinatarios: las cuentas de la casa (con quienes
     * puede hablar, ver `Directorio`) y las direcciones de fuera con las que
     * ya se escribió. Coincide con nombre, usuario o dirección.
     *
     * @param \Closure(list<int>): list<int> $conversables filtra ids de usuario por el Directorio
     *
     * @return list<array{direccion: string, nombre: ?string, interno: bool}>
     */
    public function contactos(CorreoCuenta $cuenta, string $q, \Closure $conversables, bool $externos): array
    {
        $q = mb_strtolower(trim($q));
        if (mb_strlen($q) < 1) {
            return [];
        }
        $like = "%" . addcslashes($q, "%_\\") . "%";
        $filas = $this->em->getConnection()->fetchAllAssociative(
            "SELECT u.id, u.username, u.nombre, u.apellido, c.local FROM usuario u LEFT JOIN correo_cuenta c ON c.usuario_id = u.id
             WHERE u.activo AND u.id <> ? AND (LOWER(u.username) LIKE ? OR LOWER(COALESCE(u.nombre, '') || ' ' || COALESCE(u.apellido, '')) LIKE ? OR LOWER(COALESCE(c.local, '')) LIKE ?)
             ORDER BY u.nombre, u.apellido LIMIT 30",
            [$cuenta->getUsuario()->getId(), $like, $like, $like],
        );
        $permitidos = array_flip($conversables(array_map(static fn(array $f) => (int) $f["id"], $filas)));
        $resultado = [];
        foreach ($filas as $f) {
            if (!isset($permitidos[(int) $f["id"]])) {
                continue;
            }
            $local = $f["local"] ?? Direcciones::localDe((string) $f["username"]);
            if ($local === "") {
                continue;
            }
            $nombre = trim(($f["nombre"] ?? "") . " " . ($f["apellido"] ?? ""));
            $resultado[] = ["direccion" => $this->cuentas->direccionDe($local), "nombre" => $nombre !== "" ? $nombre : (string) $f["username"], "interno" => true];
        }
        if ($externos) {
            $vistas = $this->em->getConnection()->fetchAllAssociative(
                "SELECT d->>'direccion' AS direccion, MAX(d->>'nombre') AS nombre FROM correo_mensaje m, jsonb_array_elements(m.para || m.cc) d
                 WHERE m.cuenta_id = ? AND m.carpeta = 'enviados' AND m.externo AND LOWER(d::text) LIKE ? GROUP BY 1 LIMIT 10",
                [$cuenta->getId(), $like],
            );
            $recibidas = $this->em->getConnection()->fetchAllAssociative(
                "SELECT de_direccion AS direccion, MAX(de_nombre) AS nombre FROM correo_mensaje WHERE cuenta_id = ? AND externo AND carpeta <> 'enviados' AND (LOWER(de_direccion) LIKE ? OR LOWER(COALESCE(de_nombre, '')) LIKE ?) GROUP BY 1 LIMIT 10",
                [$cuenta->getId(), $like, $like],
            );
            $ya = array_flip(array_column($resultado, "direccion"));
            foreach ([...$vistas, ...$recibidas] as $v) {
                $d = strtolower((string) $v["direccion"]);
                if (!isset($ya[$d]) && Direcciones::valida($d)) {
                    $ya[$d] = true;
                    $resultado[] = ["direccion" => $d, "nombre" => $v["nombre"] ?: null, "interno" => false];
                }
            }
        }

        return array_slice($resultado, 0, 20);
    }

    /** Las otras pestañas del usuario vuelven a pedir el estado. */
    public function avisar(CorreoCuenta $cuenta, ?int $nuevo = null, ?array $datos = null): void
    {
        $this->avisos->avisar([(int) $cuenta->getUsuario()->getId()], "correo", ["nuevo" => $nuevo, ...($datos ?? [])]);
    }

    /** @return array<string, mixed> */
    public function resumen(CorreoMensaje $m, bool $conAdjuntos): array
    {
        return [
            "id" => $m->getId(),
            "carpeta" => $m->getCarpeta()->value,
            "de" => ["direccion" => $m->getDeDireccion(), "nombre" => $m->getDeNombre()],
            "para" => $m->getPara(),
            "asunto" => $m->getAsunto(),
            "extracto" => Redaccion::extracto($m->getTexto()),
            "fecha" => $m->getFecha()->format(\DateTimeInterface::ATOM),
            "leido" => $m->isLeido(),
            "destacado" => $m->isDestacado(),
            "externo" => $m->isExterno(),
            "conAdjuntos" => $conAdjuntos,
            "envio" => $m->getEnvio()?->value,
        ];
    }

    /** @param list<string> $rutas */
    private function quitar(CorreoMensaje $m, array &$rutas): void
    {
        foreach ($m->getAdjuntos() as $a) {
            $rutas[] = $a->getRuta();
            $this->em->remove($a);
        }
        $this->em->remove($m);
    }

    private function fallidos(CorreoCuenta $cuenta): int
    {
        return (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM correo_mensaje WHERE cuenta_id = ? AND carpeta = 'enviados' AND envio = 'fallido'", [$cuenta->getId()]);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, true>
     */
    private function conAdjuntos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $con = $this->em->getConnection()->fetchFirstColumn(
            sprintf("SELECT DISTINCT mensaje_id FROM correo_adjunto WHERE NOT en_linea AND mensaje_id IN (%s)", implode(",", array_map("intval", $ids))),
        );

        return array_fill_keys(array_map("intval", $con), true);
    }
}
