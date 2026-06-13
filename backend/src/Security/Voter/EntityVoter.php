<?php

namespace App\Security\Voter;

use App\Entity\Usuario;
use App\Security\PermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EntityVoter extends Voter {

    public const CREATE = 'create';
    public const READ   = 'read';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    public function __construct(
        private PermissionManager $permissionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool {
        if (!in_array($attribute, [self::CREATE, self::READ, self::UPDATE, self::DELETE], true)) {
            return false;
        }
        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $entityName = $this->resolveEntityName($subject);
        $actionCode = strtolower($entityName) . '.' . $attribute;

        return $this->permissionManager->can($user, $actionCode);
    }

    private function resolveEntityName(mixed $subject): string {
        if ($subject === null) {
            return 'unknown';
        }

        if (is_string($subject)) {
            $parts = explode('\\', $subject);
            return end($parts);
        }

        if (is_object($subject)) {
            $class = get_class($subject);
            $parts = explode('\\', $class);
            return end($parts);
        }

        return 'unknown';
    }
}
