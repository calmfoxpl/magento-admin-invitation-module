<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Core\Invitation;

/**
 * The secret in an invitation link. The link carries the token, the database only its hash:
 * a copy of the table does not let anyone accept somebody else's invitation.
 */
final class Token
{
    /** Bytes of randomness; the token is their hex form (64 characters). */
    private const BYTES = 32;

    public static function generate(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    public static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /** Constant-time comparison of a link's token with the stored hash. */
    public static function matches(#[\SensitiveParameter] string $token, string $storedHash): bool
    {
        if ('' === $token || '' === $storedHash) {
            return false;
        }

        return hash_equals($storedHash, self::hash($token));
    }

    /** A token from a link: hex only, otherwise it is not ours. */
    public static function isWellFormed(string $token): bool
    {
        return 1 === preg_match('/^[a-f0-9]{' . (self::BYTES * 2) . '}$/', $token);
    }
}
