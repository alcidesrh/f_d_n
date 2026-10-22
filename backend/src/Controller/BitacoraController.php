<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Acceso\Ubicador;
use App\Entity\Role;
use App\Bitacora\ConsultaBitacora;
use App\Bitacora\TipoRegistro;
use App\Entity\BoletoAsiento;
use App\Entity\Encomienda;
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
 * Bitácora de salidas, boletos, encomiendas, usuarios y roles:
 * `GET /api/bitacora/{salida|boleto|encomienda|usuario|rol}/{id}`. La de una
 * salida la ve quien puede ver salidas de su empresa (`salida.ver`); la de un
 * boleto, quien puede ver su venta; la de una encomienda, quien ve
 * encomiendas de su empresa (`encomienda.ver`); las de usuarios y roles
 * (sus accesos), quien ve accesos (`iam.ver`). ADR-039.
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
            TipoRegistro::ENCOMIENDA => $this->etiquetaEncomienda($id),
            TipoRegistro::USUARIO => $this->etiquetaUsuario($id),
            TipoRegistro::ROL => $this->etiquetaRol($id),
        };

        return $this->json([
            "tipo" => $registro->value,
            "id" => $id,
            "etiqueta" => $etiqueta,
            "entradas" => $this->consulta->de($registro, $id),
        ]);
    }

    private function etiquetaUsuario(int $id): string
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_VER->value);
        $u = $this->em->find(Usuario::class, $id);

        return $u === null ? sprintf("Usuario %d", $id) : sprintf("Accesos de %s", $u->getLabel());
    }

    private function etiquetaRol(int $id): string
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_VER->value);
        $r = $this->em->find(Role::class, $id);

        return $r === null ? sprintf("Rol %d", $id) : sprintf("Rol %s", $r->getNombre());
    }

    private function etiquetaEncomienda(int $id): string
    {
        $this->denyAccessUnlessGranted(EncomiendaController::VER);
        $e = $this->em->find(Encomienda::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EncomiendaController::VER, Ubicacion::empresa($e->getEmpresa()?->getId()));

        return sprintf("Encomienda %s · %s → %s · %s", $e->getCodigo(), $e->getOrigen()?->getNombre(), $e->getDestino()?->getNombre(), $e->getCreada()->format("d/m/Y H:i"));
    }

    private function etiquetaSalida(int $id): string
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);
        $salida = $this->em->find(Salida::class, $id);
        if ($salida === null) {
            // Eliminada: la bitácora sigue ahí.
            return sprintf("Salida %d", $id);
        }
        $this->denyAccessUnlessGranted(SalidaController::VER, Ubicador::salida($salida));

        return sprintf(
            "Salida %d · %s → %s · %s",
            $id,
            $salida->getRuta()?->getOrigen()?->getNombre(),
            $salida->getRuta()?->getDestino()?->getNombre(),
            $salida->getFecha()?->format("d/m/Y H:i"),
        );
    }

    private function etiquetaBoleto(int $id, Usuario $usuario): string
    {
        $boleto = $this->em->find(BoletoAsiento::class, $id) ?? throw $this->createNotFoundException();
        if (!$this->acceso->puedeVer($boleto->getBoletoVenta(), $usuario)) {
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
