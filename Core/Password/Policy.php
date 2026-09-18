<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Core\Password;

/**
 * What an administrator's password must look like: a minimum length, a minimum strength
 * estimate and, optionally, absence from known data breaches. Returns violation codes; the
 * Magento side turns them into translated messages.
 */
final class Policy
{
    public const TOO_SHORT = 'too_short';

    public const TOO_WEAK = 'too_weak';

    public const COMPROMISED = 'compromised';

    /**
     * @param int<8, max> $minLength
     * @param int<1, 4> $minStrength
     * @param int<12, 64> $generatedLength
     * @param (callable(string): bool)|null $isCompromised null when the breach check is off
     */
    public function __construct(
        private readonly int $minLength,
        private readonly int $minStrength,
        private readonly int $generatedLength,
        private readonly mixed $isCompromised = null,
    ) {
        if ($minLength < 8) {
            throw new \InvalidArgumentException('The minimum password length cannot be lower than 8.');
        }
        if ($minStrength < 1 || $minStrength > 4) {
            throw new \InvalidArgumentException('The minimum strength must be between 1 and 4.');
        }
        if ($generatedLength < 12 || $generatedLength > 64) {
            throw new \InvalidArgumentException('The generated password length must be between 12 and 64.');
        }
        if (null !== $isCompromised && !\is_callable($isCompromised)) {
            throw new \InvalidArgumentException('The breach check must be callable or null.');
        }
    }

    /**
     * Violation codes in the order they should be shown; an empty list means the password is fine.
     * The breach check is asked last and only for passwords that pass the cheap rules.
     *
     * @return list<self::TOO_SHORT|self::TOO_WEAK|self::COMPROMISED>
     */
    public function violations(#[\SensitiveParameter] string $password): array
    {
        $violations = [];

        if (mb_strlen($password) < $this->minLength) {
            $violations[] = self::TOO_SHORT;
        }
        if (StrengthEstimator::estimate($password) < $this->minStrength) {
            $violations[] = self::TOO_WEAK;
        }
        if ([] === $violations && null !== $this->isCompromised && ($this->isCompromised)($password)) {
            $violations[] = self::COMPROMISED;
        }

        return $violations;
    }

    public function minLength(): int
    {
        return $this->minLength;
    }

    public function minStrength(): int
    {
        return $this->minStrength;
    }

    public function generatedLength(): int
    {
        return $this->generatedLength;
    }
}
