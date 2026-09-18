<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests\Core\Password;

use Calmfox\AdminInvitation\Core\Password\BreachRange;
use PHPUnit\Framework\TestCase;

final class BreachRangeTest extends TestCase
{
    /** The whole point of the protocol: only five characters of a hash ever leave the shop. */
    public function testOnlyTheFirstFiveHashCharactersAreSent(): void
    {
        $prefix = BreachRange::prefix('password');

        self::assertSame(5, \strlen($prefix));
        self::assertSame(strtoupper(substr(sha1('password'), 0, 5)), $prefix);
        // everything the request carries is the endpoint plus those five characters
        self::assertSame(BreachRange::ENDPOINT . $prefix, BreachRange::url('password'));
        self::assertStringNotContainsString(substr(sha1('password'), 5), BreachRange::url('password'));
    }

    public function testAMatchingSuffixIsFound(): void
    {
        self::assertTrue(BreachRange::contains(self::response('password', 3730), 'password'));
    }

    public function testAnotherPasswordInTheSameRangeIsNotAMatch(): void
    {
        self::assertFalse(BreachRange::contains(self::response('password', 3730), 'Xk7!pQ2@mZ9#tR4$vB6&'));
    }

    /** The API pads its answers with zero-count lines; those are not breaches. */
    public function testPaddingLinesAreIgnored(): void
    {
        $body = self::response('password', 0);

        self::assertFalse(BreachRange::contains($body, 'password'));
    }

    public function testAnEmptyOrGarbledAnswerIsNotAMatch(): void
    {
        self::assertFalse(BreachRange::contains('', 'password'));
        self::assertFalse(BreachRange::contains("nonsense\nwithout colons", 'password'));
    }

    public function testCarriageReturnsAndLowercaseSuffixesAreAccepted(): void
    {
        $suffix = strtolower(substr(sha1('password'), 5));

        self::assertTrue(BreachRange::contains("0000000000000000000000000000000000A:1\r\n" . $suffix . ":42\r\n", 'password'));
    }

    private static function response(string $password, int $count): string
    {
        $suffix = strtoupper(substr(sha1($password), 5));

        return "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n" . $suffix . ':' . $count . "\r\n";
    }
}
