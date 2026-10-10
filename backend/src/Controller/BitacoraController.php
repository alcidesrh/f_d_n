<?php

declare(strict_types=1);

namespace App\Controller;

use App\Bitacora\ConsultaBitacora;
use App\Bitacora\TipoRegistro;
use App\Entity\BoletoAsiento;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Venta\Acceso\AccesoVentas;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Bitácora de salidas y boletos: `GET /api/bitacora/{salida|boleto}/{id}`.
 * La de una salida la ve quien puede ver salidas (`salida.ver`); la de un
 * boleto, quien puede ver su venta o leer boletos.
 */
#[AsController]
#[Route("/api/bitacora", name: "api_bitacora_")]
final class BitacoraController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConsultaBitacora $consulta,
        private readonly AccesoVentas $acceso,
    ) {}

    #[Route("/{tipo}/{id<\d+>}", name: "ver", methods: ["GET"])]
    public function ver(string $tipo, int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $registro = TipoRegistro::tryFrom($tipo) ?? throw $this->createNotFoundException();

        $etiqueta = match ($registro) {
            TipoRegistro::SALIDA => $this->etiquetaSalida($id),
            TipoRegistro::BOLETO => $this->etiquetaBoleto($id, $usuario),
        };

        return $this->json([
            "tipo" => $registro->value,
            "id" => $id,
            "etiqueta" => $etiqueta,
            "entradas" => $this->consulta->de($registro, $id),
        ]);
    }

    private function etiquetaSalida(int $id): string
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);
        $salida = $this->em->find(Salida::class, $id);
        if ($salida === null) {
            // Eliminada: la bitácora sigue ahí.
            return sprintf("Salida %d", $id);
        }

        return sprintf(
            "Salida %d · %s → %s · %s",
            $id,
            $salida->getTrayecto()?->getOrigen()?->getNombre(),
            $salida->getTrayecto()?->getDestino()?->getNombre(),
            $salida->getFecha()?->format("d/m/Y H:i"),
        );
    }

    private function etiquetaBoleto(int $id, Usuario $usuario): string
    {
        $boleto = $this->em->find(BoletoAsiento::class, $id) ?? throw $this->createNotFoundException();
        if (!$this->acceso->puedeVer($boleto->getBoletoVenta(), $usuario) && !$this->isGranted("read", BoletoAsiento::class)) {
            throw $this->createAccessDeniedException();
        }

        return sprintf(
            "Boleto %d · asiento %s · %s → %s · %s",
            $id,
            $boleto->getAsiento()?->getNumero(),
            $boleto->getTrayecto()?->getOrigen()?->getNombre(),
            $boleto->getTrayecto()?->getDestino()?->getNombre(),
            $boleto->getSalida()?->getFecha()?->format("d/m/Y H:i"),
        );
    }
}
