<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Core\Invitation;

/** How long a link works. Stored as an absolute instant, so the check needs no configuration. */
final class Validity
{
    public static function invitationExpiry(\DateTimeImmutable $now, int $days): \DateTimeImmutable
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('An invitation must be valid for at least one day.');
        }

        return $now->add(new \DateInterval(sprintf('P%dD', $days)));
    }

    public static function passwordResetExpiry(\DateTimeImmutable $now, int $hours): \DateTimeImmutable
    {
        if ($hours < 1) {
            throw new \InvalidArgumentException('A password reset link must be valid for at least one hour.');
        }

        return $now->add(new \DateInterval(sprintf('PT%dH', $hours)));
    }

    public static function isExpired(\DateTimeImmutable $expiresAt, \DateTimeImmutable $now): bool
    {
        return $expiresAt <= $now;
    }
}
