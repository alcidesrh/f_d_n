<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

use App\Entity\CredencialPago;
use App\Entity\Empresa;
use App\Venta\CifradoCredenciales;
use Doctrine\ORM\EntityManagerInterface;

/** Comercio de cada empresa en Cybersource (`CredencialPago`, secreto cifrado). */
final class CredencialesCybersource
{
    private const CONTEXTO = "fdn-credencial-pago";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CifradoCredenciales $cifrado,
    ) {}

    /**
     * @throws \RuntimeException si la empresa no tiene comercio configurado
     */
    public function de(int $empresaId): Comercio
    {
        $credencial = $this->em->getRepository(CredencialPago::class)->findOneBy(["empresa" => $empresaId])
            ?? throw new \RuntimeException(sprintf("La empresa %d no tiene comercio en la pasarela (app:pago:credencial).", $empresaId));
        $secreto = $this->cifrado->descifrar(self::CONTEXTO, $credencial->getSecretoCifrado())
            ?? throw new \RuntimeException("No se pudo leer la llave de la pasarela (¿cambió APP_SECRET?). Vuelva a cargarla con app:pago:credencial.");

        return new Comercio($credencial->getComercio(), $credencial->getLlave(), $secreto);
    }

    public function guardar(Empresa $empresa, string $comercio, string $llave, string $secreto): void
    {
        $cifrado = $this->cifrado->cifrar(self::CONTEXTO, $secreto);
        $actual = $this->em->getRepository(CredencialPago::class)->findOneBy(["empresa" => $empresa]);
        if ($actual !== null) {
            $actual->cambiar($comercio, $llave, $cifrado);
        } else {
            $this->em->persist(new CredencialPago($empresa, $comercio, $llave, $cifrado));
        }
        $this->em->flush();
    }
}
