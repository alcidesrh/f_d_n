<?php

namespace App\Security;

use App\Acceso\Suplantacion\SuplantacionActual;
use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class ApiTokenHandler implements AccessTokenHandlerInterface {
  public function __construct(
    private ApiTokenRepository $apiTokenRepository,
    private SuplantacionActual $suplantacion,
  ) {
  }

  public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge {
    $token = $this->apiTokenRepository->findOneBy(['token' => $accessToken]);
    if (!$token) {
      throw new BadCredentialsException();
    }
    if (!$token->isValid()) {
      throw new CustomUserMessageAuthenticationException($token->esSuplantacion() ? 'La suplantación terminó o venció.' : 'La sessión a caducado. ');
    }
    // Quien suplanta deja de poder hacerlo si lo desactivan.
    if ($token->esSuplantacion() && !$token->getSuplantadoPor()?->isActivo()) {
      throw new CustomUserMessageAuthenticationException('La suplantación terminó o venció.');
    }
    $this->suplantacion->fijar($token);
    return new UserBadge($token->getUsuario()->getUserIdentifier(), fn() => $token->getUsuario());
  }
}
