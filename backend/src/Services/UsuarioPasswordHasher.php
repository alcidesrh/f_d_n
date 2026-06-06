<?php
// api/src/State/UsuarioPasswordHasher.php

namespace App\Services;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<Usuario, Usuario|void>
 */
final readonly class UsuarioPasswordHasher implements ProcessorInterface {
  public function __construct(
    private ProcessorInterface $processor,
    private UserPasswordHasherInterface $passwordHasher
  ) {
  }

  /**
   * @param Usuario $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario {
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
