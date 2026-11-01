<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

use App\Entity\ConfiguracionCybersource;
use App\Entity\CredencialPago;
use App\Entity\Empresa;
use App\Entity\Usuario;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Forcon\GestionForcon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Ver y cambiar `ConfiguracionCybersource` (ADR-047): lo usan la
 * administración (`ConfiguracionProveedoresController`) y
 * `app:pago:configuracion`, con la misma validación.
 */
final class GestionCybersource
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesCybersource $ajustes,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, mixed> */
    public function datos(): array
    {
        $c = $this->ajustes->actual();
        $conComercio = [];
        foreach ($this->em->getRepository(CredencialPago::class)->findAll() as $credencial) {
            $conComercio[spl_object_id($credencial->getEmpresa())] = $credencial->getComercio();
        }

        return [
            "activo" => $c->isActivo(),
            "url" => $c->getUrl(),
            "orgId" => $c->getOrgId(),
            "actualizadaEn" => $c->getActualizadaEn()?->format(\DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getLabel(),
            "empresas" => array_map(
                static fn(Empresa $e) => [
                    "nombre" => (string) ($e->getNombreCorto() ?: $e->getNombre()),
                    "comercio" => $conComercio[spl_object_id($e)] ?? null,
                ],
                $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            ),
        ];
    }

    /**
     * Cambia solo lo que viene: `activo`, `url` y `orgId` (vacío = sin huella).
     *
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada si algo no es válido (no guarda nada)
     */
    public function guardar(array $datos, ?Usuario $por): ConfiguracionCybersource
    {
        $config = $this->em->find(ConfiguracionCybersource::class, ConfiguracionCybersource::ID);
        if ($config === null) {
            $config = new ConfiguracionCybersource();
            $this->em->persist($config);
        }

        if (array_key_exists("activo", $datos)) {
            $config->setActivo((bool) $datos["activo"]);
        }
        if (array_key_exists("url", $datos)) {
            $config->setUrl(GestionForcon::url($datos["url"]));
        }
        if (array_key_exists("orgId", $datos)) {
            $org = trim((string) ($datos["orgId"] ?? ""));
            if (mb_strlen($org) > 100 || preg_match('/\s/', $org)) {
                throw new VentaRechazada("El Org ID no es válido.", "org_id_invalido");
            }
            $config->setOrgId($org);
        }

        $config->actualizada($por, $this->reloj->now());
        $this->em->flush();

        return $config;
    }
}
