<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Password;

/**
 * Whether the policy is being enforced right now regardless of configuration. The invitation
 * page always applies it; with "Apply to all administrator password changes" turned off, the
 * other pages do not.
 */
class PolicyScope
{
    private bool $enforced = false;

    /** @template T @param callable(): T $callback @return T */
    public function enforce(callable $callback): mixed
    {
        $previous = $this->enforced;
        $this->enforced = true;

        try {
            return $callback();
        } finally {
            $this->enforced = $previous;
        }
    }

    public function isEnforced(): bool
    {
        return $this->enforced;
    }
}
