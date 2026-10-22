<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un usuario desactivado no entra, ni con contraseña ni con un token que ya
 * tenía (`user_checker` del firewall).
 */
final class UsuarioActivo implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Usuario && !$user->isActivo()) {
            throw new CustomUserMessageAccountStatusException('Su usuario está desactivado: consulte con un administrador.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void {}
}
