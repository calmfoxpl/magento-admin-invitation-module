<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Core\Password;

/**
 * The k-anonymity protocol of haveibeenpwned.com: the server is asked for every hash starting
 * with the first five hex characters of the password's SHA-1 and answers with the remaining
 * 35 characters of each match and how often it was seen. The password itself never leaves
 * the shop. This class does the hashing and the matching; fetching is the caller's job.
 */
final class BreachRange
{
    public const ENDPOINT = 'https://api.pwnedpasswords.com/range/';

    /** The first five characters of the uppercase SHA-1: the only thing sent to the API. */
    public static function prefix(#[\SensitiveParameter] string $password): string
    {
        return substr(self::hash($password), 0, 5);
    }

    public static function url(#[\SensitiveParameter] string $password): string
    {
        return self::ENDPOINT . self::prefix($password);
    }

    /**
     * Whether the API's answer for the password's prefix contains the password itself,
     * seen at least $threshold times. Padding lines ("...:0") never match.
     */
    public static function contains(string $responseBody, #[\SensitiveParameter] string $password, int $threshold = 1): bool
    {
        $hash = self::hash($password);
        $prefix = substr($hash, 0, 5);

        foreach (preg_split('/\r\n|\n|\r/', $responseBody) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line || !str_contains($line, ':')) {
                continue;
            }
            [$suffix, $count] = explode(':', $line, 2);
            if (hash_equals($hash, $prefix . strtoupper(trim($suffix))) && (int) trim($count) >= $threshold) {
                return true;
            }
        }

        return false;
    }

    private static function hash(#[\SensitiveParameter] string $password): string
    {
        return strtoupper(sha1($password));
    }
}
