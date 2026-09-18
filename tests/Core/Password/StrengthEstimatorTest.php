<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests\Core\Password;

use Calmfox\AdminInvitation\Core\Password\StrengthEstimator;
use PHPUnit\Framework\TestCase;

final class StrengthEstimatorTest extends TestCase
{
    /** The case length alone cannot catch: long, and worth nothing. */
    public function testARepeatedCharacterIsVeryWeakHoweverLong(): void
    {
        self::assertSame(StrengthEstimator::VERY_WEAK, StrengthEstimator::estimate(str_repeat('a', 24)));
    }

    public function testAnEmptyPasswordIsVeryWeak(): void
    {
        self::assertSame(StrengthEstimator::VERY_WEAK, StrengthEstimator::estimate(''));
    }

    public function testAMixedTwentyCharacterPasswordIsAtLeastStrong(): void
    {
        self::assertGreaterThanOrEqual(StrengthEstimator::STRONG, StrengthEstimator::estimate('Xk7!pQ2@mZ9#tR4$vB6&'));
    }

    /** More variety scores higher than the same length in one alphabet. */
    public function testVarietyBeatsPlainLowercase(): void
    {
        $plain = StrengthEstimator::estimate('abcdefghijklmnop');
        $mixed = StrengthEstimator::estimate('aB3!eF6@iJ9#mN2$');

        self::assertGreaterThan($plain, $mixed);
    }

    /** The scale used by the configuration is 0 to 4 and nothing else. */
    public function testTheScoreStaysWithinTheScale(): void
    {
        foreach (['', 'a', 'password', 'Tr0ub4dor&3', str_repeat('Zq7!', 16)] as $password) {
            $score = StrengthEstimator::estimate($password);
            self::assertGreaterThanOrEqual(StrengthEstimator::VERY_WEAK, $score);
            self::assertLessThanOrEqual(StrengthEstimator::VERY_STRONG, $score);
        }
    }
}
