<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use App\Entity\User;
use App\Repository\UsuarioRepository;
use App\Services\ServerSentEvent;


final class UserByUsernameResolver implements QueryItemResolverInterface {

  public function __construct(private UsuarioRepository $userRepository, private ServerSentEvent $serverSentEvent) {
  }
  /**
   */
  public function __invoke($item, array $context): object {

    if ($arg = $context['args']['username'] ?? null) {
      if ($user = $this->userRepository->findOneBy(['id' => $arg]))
        return $user;
    }
    $this->serverSentEvent->error([
      'severity' => 'error',
      'detail' => "El usuario <b>{$arg}</b> no existe",
      'summary' => 'No se pudo iniciar la sesión.'
    ]);
    throw new \Exception('No existe el usuario: ' . $arg);
    $user = new User();
    $user->setUsername('error')->setNombre('error')->setId(0);
    return $user;
  }
}
