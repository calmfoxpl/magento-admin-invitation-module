<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model;

/** "Now" in UTC, in one place, so tests and the database agree on the timezone. */
class Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
