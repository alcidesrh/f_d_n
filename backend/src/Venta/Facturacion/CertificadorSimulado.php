<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Certificador de desarrollo/pruebas: emite DTEs ficticios sin salir a la
 * red. Para probar los flujos de error:
 *
 * - receptor con NIT `0` → rechazo del documento (no recuperable);
 * - `FEL_SIMULADO_FALLA=1` → sin respuesta (recuperable), también al anular.
 */
final class CertificadorSimulado implements CertificadorFel
{
    public function __construct(
        #[Autowire(env: "bool:default::FEL_SIMULADO_FALLA")]
        private readonly ?bool $falla = false,
    ) {}

    public function certificar(SolicitudDte $solicitud): DteCertificado
    {
        if ($this->falla) {
            throw CertificacionFallida::sinRespuesta();
        }
        if (trim($solicitud->receptorNit) === "0") {
            throw new CertificacionFallida(
                "El NIT del receptor no es válido según la SAT.",
                false,
                "nit_invalido",
            );
        }

        $uuid = strtoupper(Uuid::v4()->toRfc4122());

        return new DteCertificado(
            uuid: $uuid,
            serie: substr($uuid, 0, 8),
            numero: (int) hexdec(substr(str_replace("-", "", $uuid), 8, 7)),
            fechaCertificacion: new \DateTimeImmutable(),
            certificadorNit: "SIMULADO",
            certificadorNombre: "Certificador simulado (desarrollo)",
        );
    }

    public function anular(SolicitudAnulacion $solicitud): void
    {
        if ($this->falla) {
            throw CertificacionFallida::sinRespuesta();
        }
    }
}
