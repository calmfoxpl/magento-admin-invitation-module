<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests\Core\Invitation;

use Calmfox\AdminInvitation\Core\Invitation\Validity;
use PHPUnit\Framework\TestCase;

final class ValidityTest extends TestCase
{
    public function testAnInvitationExpiresAfterTheConfiguredDays(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 10:00:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-03-04 10:00:00', Validity::invitationExpiry($now, 3)->format('Y-m-d H:i:s'));
    }

    public function testAPasswordResetLinkExpiresAfterTheConfiguredHours(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 10:00:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-03-01 12:00:00', Validity::passwordResetExpiry($now, 2)->format('Y-m-d H:i:s'));
    }

    /** The moment of expiry is already too late; a link is never valid "up to and including". */
    public function testALinkIsExpiredAtItsOwnExpiryMoment(): void
    {
        $moment = new \DateTimeImmutable('2026-03-04 10:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(Validity::isExpired($moment, $moment));
        self::assertTrue(Validity::isExpired($moment, $moment->modify('+1 second')));
        self::assertFalse(Validity::isExpired($moment, $moment->modify('-1 second')));
    }

    public function testAValidityOfLessThanADayIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Validity::invitationExpiry(new \DateTimeImmutable(), 0);
    }
}
