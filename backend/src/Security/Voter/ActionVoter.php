<?php

namespace App\Security\Voter;

use App\Entity\Usuario;
use App\Security\PermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class ActionVoter extends Voter {

    public function __construct(
        private PermissionManager $permissionManager,
        private RoleHierarchyInterface $roleHierarchy,
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

        // role_hierarchy: ROLE_SUPER_ADMIN también cuenta como ROLE_ADMIN.
        if (in_array('ROLE_ADMIN', $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true)) {
            return true;
        }

        return $this->permissionManager->can($user, $attribute);
    }
}
