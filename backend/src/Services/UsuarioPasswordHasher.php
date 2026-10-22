<?php

namespace App\Services;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Acceso\Administracion\AdministracionAccesos;
use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Entity\Usuario;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crear y editar usuarios por la API genérica: cifra la contraseña y, al
 * editar a otro, exige dominarlo —poder otorgarle cada asignación,
 * ADR-039— y, si cambia su agencia (que es su alcance), `iam.asignar`.
 *
 * @implements ProcessorInterface<Usuario, Usuario|void>
 */
final readonly class UsuarioPasswordHasher implements ProcessorInterface {
  public function __construct(
    private ProcessorInterface $processor,
    private UserPasswordHasherInterface $passwordHasher,
    private AdministracionAccesos $accesos,
    private Autorizacion $autorizacion,
  ) {
  }

  /**
   * @param Usuario $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario {
    $previo = $context['previous_data'] ?? null;
    if ($previo instanceof Usuario && $data->getId() !== null) {
      $motivo = $this->accesos->rechazoSobre($data);
      if ($motivo !== null) {
        throw new AccessDeniedHttpException($motivo);
      }
      if ($previo->getAgencia()?->getId() !== $data->getAgencia()?->getId() && !$this->autorizacion->puede(Capacidad::IAM_ASIGNAR)) {
        throw new AccessDeniedHttpException('Cambiar la agencia de un usuario cambia su alcance: hace falta poder asignar roles.');
      }
    }

    if (!$data->getPlainPassword()) {
      return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    $hashedPassword = $this->passwordHasher->hashPassword(
      $data,
      $data->getPlainPassword()
    );
    $data->setPassword($hashedPassword);

    // To avoid leaving sensitive data like the plain password in memory or logs, we manually clear it after hashing.
    $data->setPlainPassword(null);

    return $this->processor->process($data, $operation, $uriVariables, $context);
  }
}
