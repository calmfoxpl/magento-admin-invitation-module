<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests\Core\Password;

use Calmfox\AdminInvitation\Core\Password\Policy;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    public function testAGoodPasswordHasNoViolations(): void
    {
        self::assertSame([], self::policy()->violations('Xk7!pQ2@mZ9#tR4$vB6&'));
    }

    public function testAShortPasswordIsReported(): void
    {
        self::assertContains(Policy::TOO_SHORT, self::policy()->violations('Xk7!pQ2@'));
    }

    public function testALongButPredictablePasswordIsReported(): void
    {
        $violations = self::policy()->violations(str_repeat('ab', 12));

        self::assertContains(Policy::TOO_WEAK, $violations);
        self::assertNotContains(Policy::TOO_SHORT, $violations);
    }

    /** The breach check costs a network call, so it is asked only about otherwise valid passwords. */
    public function testTheBreachCheckIsNotAskedAboutAnAlreadyRejectedPassword(): void
    {
        $asked = false;
        $policy = new Policy(12, 3, 20, function () use (&$asked): bool {
            $asked = true;

            return true;
        });

        $policy->violations('short');

        self::assertFalse($asked);
    }

    public function testAKnownBreachedPasswordIsReported(): void
    {
        $policy = new Policy(12, 3, 20, static fn (): bool => true);

        self::assertSame([Policy::COMPROMISED], $policy->violations('Xk7!pQ2@mZ9#tR4$vB6&'));
    }

    public function testTheBreachCheckIsSkippedWhenItIsTurnedOff(): void
    {
        self::assertSame([], self::policy()->violations('Xk7!pQ2@mZ9#tR4$vB6&'));
    }

    public function testAMinimumLengthBelowEightIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Policy(6, 3, 20);
    }

    public function testAStrengthOutsideTheScaleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Policy(12, 5, 20);
    }

    public function testAGeneratedLengthOutsideTheRangeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Policy(12, 3, 128);
    }

    /** Multi-byte passwords are measured in characters, not bytes. */
    public function testLengthIsCountedInCharacters(): void
    {
        $policy = new Policy(12, 1, 20);

        self::assertNotContains(Policy::TOO_SHORT, $policy->violations('zażółćgęśląjaźń'));
    }

    private static function policy(): Policy
    {
        return new Policy(12, 3, 20);
    }
}
