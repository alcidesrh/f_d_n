<?php

namespace App\Security\Voter;

use App\Entity\Usuario;
use App\Security\PermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ActionVoter extends Voter {

    public function __construct(
        private PermissionManager $permissionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool {
        if (!is_string($attribute)) {
            return false;
        }
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $this->permissionManager->can($user, $attribute);
    }
}
