<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Tests\Core\Invitation;

use Calmfox\AdminInvitation\Core\Invitation\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testEachTokenIsDifferentAndWellFormed(): void
    {
        $first = Token::generate();
        $second = Token::generate();

        self::assertNotSame($first, $second);
        self::assertTrue(Token::isWellFormed($first));
        self::assertSame(64, \strlen($first));
    }

    /** The database never holds the secret from the link. */
    public function testTheStoredHashIsNotTheToken(): void
    {
        $token = Token::generate();

        self::assertNotSame($token, Token::hash($token));
        self::assertTrue(Token::matches($token, Token::hash($token)));
    }

    public function testAnotherTokenDoesNotMatch(): void
    {
        self::assertFalse(Token::matches(Token::generate(), Token::hash(Token::generate())));
    }

    /** Empty values must never be accepted as "equal". */
    public function testEmptyValuesNeverMatch(): void
    {
        self::assertFalse(Token::matches('', Token::hash('x')));
        self::assertFalse(Token::matches('x', ''));
        self::assertFalse(Token::matches('', ''));
    }

    public function testMalformedTokensAreRejectedBeforeAnyLookup(): void
    {
        foreach (['', 'abc', 'Z' . str_repeat('a', 63), str_repeat('a', 63), str_repeat('A', 64)] as $candidate) {
            self::assertFalse(Token::isWellFormed($candidate), $candidate);
        }
    }
}
