<?php

declare(strict_types=1);

namespace App\Correo;

use App\Entity\CorreoCuenta;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La cuenta de correo de cada usuario: `{usuario}@{CORREO_DOMINIO}`. Se
 * crea sola la primera vez que se necesita (abre el correo, le escriben o
 * le llega uno de fuera). Si dos usuarios dan la misma parte local, el
 * segundo lleva un número (`ana2@`).
 */
final class Cuentas
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Dominios $dominios,
    ) {}

    public function de(Usuario $usuario): CorreoCuenta
    {
        $cuenta = $this->em->getRepository(CorreoCuenta::class)->findOneBy(["usuario" => $usuario]);
        if ($cuenta !== null) {
            return $cuenta;
        }
        $base = Direcciones::localDe($usuario->getUsername()) ?: "usuario" . $usuario->getId();
        $local = $base;
        for ($n = 2; $this->em->getRepository(CorreoCuenta::class)->findOneBy(["local" => $local]) !== null; $n++) {
            $local = $base . $n;
        }
        $cuenta = new CorreoCuenta($usuario, $local);
        $this->em->persist($cuenta);
        $this->em->flush();

        return $cuenta;
    }

    public function direccion(CorreoCuenta $cuenta): string
    {
        return $this->dominios->direccion($cuenta->getLocal());
    }

    public function direccionDe(string $local): string
    {
        return $this->dominios->direccion($local);
    }

    /** Las direcciones con las que llega correo a la cuenta (la del remitente y la de entrada). @return list<string> */
    public function propias(CorreoCuenta $cuenta): array
    {
        return array_values(array_unique(array_filter([$this->direccion($cuenta), $this->dominios->responderA($cuenta->getLocal())])));
    }

    /**
     * La cuenta de una dirección de la casa: la que ya existe o la del
     * usuario activo cuyo nombre de usuario da esa parte local. Null si no es
     * de la casa o no hay a quién.
     */
    public function deDireccion(string $direccion): ?CorreoCuenta
    {
        $local = $this->dominios->local($direccion);
        if ($local === null || $local === "") {
            return null;
        }
        $cuenta = $this->em->getRepository(CorreoCuenta::class)->findOneBy(["local" => $local]);
        if ($cuenta !== null) {
            return $cuenta->getUsuario()->isActivo() ? $cuenta : null;
        }
        $usuario = $this->em->createQueryBuilder()
            ->select("u")->from(Usuario::class, "u")
            ->where("LOWER(u.username) = :local")->setParameter("local", $local)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (!$usuario instanceof Usuario || !$usuario->isActivo() || Direcciones::localDe($usuario->getUsername()) !== $local) {
            return null;
        }

        return $this->de($usuario);
    }
}
