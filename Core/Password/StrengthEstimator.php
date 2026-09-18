<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Core\Password;

/**
 * Entropy estimate of a password, the same formula as Symfony's PasswordStrength constraint,
 * so the policy means the same thing in the Sylius and Magento packages: the size of the
 * character pool the password draws from, times the number of distinct characters, plus a
 * little for repeats. "aaaaaaaaaaaa" scores very weak, a twelve-character mix scores strong.
 */
final class StrengthEstimator
{
    public const VERY_WEAK = 0;

    public const WEAK = 1;

    public const MEDIUM = 2;

    public const STRONG = 3;

    public const VERY_STRONG = 4;

    /** @return self::VERY_WEAK|self::WEAK|self::MEDIUM|self::STRONG|self::VERY_STRONG */
    public static function estimate(#[\SensitiveParameter] string $password): int
    {
        $length = \strlen($password);
        if (0 === $length) {
            return self::VERY_WEAK;
        }

        $counts = count_chars($password, 1);
        $distinct = \count($counts);

        $control = $digit = $upper = $lower = $symbol = $other = 0;
        foreach (array_keys($counts) as $byte) {
            match (true) {
                $byte < 32 || 127 === $byte => $control = 33,
                48 <= $byte && $byte <= 57 => $digit = 10,
                65 <= $byte && $byte <= 90 => $upper = 26,
                97 <= $byte && $byte <= 122 => $lower = 26,
                128 <= $byte => $other = 128,
                default => $symbol = 33,
            };
        }

        $pool = $lower + $upper + $digit + $symbol + $control + $other;
        $entropy = $distinct * log($pool, 2) + ($length - $distinct) * log($distinct, 2);

        return match (true) {
            $entropy >= 120 => self::VERY_STRONG,
            $entropy >= 100 => self::STRONG,
            $entropy >= 80 => self::MEDIUM,
            $entropy >= 60 => self::WEAK,
            default => self::VERY_WEAK,
        };
    }
}
