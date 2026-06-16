<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class LegacySha512PasswordHasher implements PasswordHasherInterface
{
    private const int ITERATIONS = 5000;
    private const string PREFIX = '$sha512$';

    public function hash(#[\SensitiveParameter] string $plainPassword, ?string $salt = null): string
    {
        throw new InvalidPasswordException('Cannot hash new passwords with legacy algorithm');
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, ?string $salt = null): bool
    {
        if (!str_starts_with($hashedPassword, self::PREFIX)) {
            return false;
        }

        $parts = explode('$', $hashedPassword);
        if (count($parts) !== 4) {
            return false;
        }

        [, , $storedHash, $legacySalt] = $parts;

        $salted = $plainPassword . '{' . $legacySalt . '}';
        $digest = hash('sha512', $salted, true);
        for ($i = 1; $i < self::ITERATIONS; $i++) {
            $digest = hash('sha512', $digest . $salted, true);
        }

        return hash_equals($storedHash, base64_encode($digest));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return str_starts_with($hashedPassword, self::PREFIX);
    }
}
