<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Entity\Bitacora;
use Doctrine\ORM\EntityManagerInterface;

/** Lectura de la bitácora de un registro, de lo más viejo a lo más nuevo. */
final class ConsultaBitacora
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return list<array{id: int, operacion: string, etiqueta: string, fecha: string, usuario: ?array{id: ?int, username: ?string, nombre: ?string}, detalle: ?array<string, mixed>}>
     */
    public function de(TipoRegistro $tipo, int $id): array
    {
        /** @var list<Bitacora> $filas */
        $filas = $this->em->createQueryBuilder()
            ->select("b", "u")
            ->from(Bitacora::class, "b")
            ->leftJoin("b.usuario", "u")
            ->where("b.entidad = :tipo AND b.registroId = :id")
            ->setParameter("tipo", $tipo->value)
            ->setParameter("id", $id)
            ->orderBy("b.fecha", "ASC")
            ->addOrderBy("b.id", "ASC")
            ->getQuery()
            ->getResult();
        $operaciones = $tipo->operaciones();

        return array_map(static function (Bitacora $b) use ($operaciones): array {
            $operacion = $operaciones::tryFrom($b->getOperacion());
            $usuario = $b->getUsuario();

            return [
                "id" => (int) $b->getId(),
                "operacion" => $b->getOperacion(),
                "etiqueta" => $operacion?->etiqueta() ?? $b->getOperacion(),
                "fecha" => $b->getFecha()->format(DATE_ATOM),
                "usuario" => $b->getUsuarioNombre() === null && $usuario === null
                    ? null
                    : [
                        "id" => $usuario?->getId(),
                        "username" => $usuario?->getUsername(),
                        "nombre" => $b->getUsuarioNombre() ?? $usuario?->getUsername(),
                    ],
                "detalle" => $b->getDetalle(),
            ];
        }, $filas);
    }
}
