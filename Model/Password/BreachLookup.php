<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Password;

use Calmfox\AdminInvitation\Core\Password\BreachRange;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Asks haveibeenpwned.com whether a password is known from a breach. When the API cannot be
 * reached the password is accepted: a broken network must not lock administrators out.
 */
class BreachLookup
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly Curl $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isCompromised(#[\SensitiveParameter] string $password): bool
    {
        try {
            $this->httpClient->setTimeout(self::TIMEOUT_SECONDS);
            $this->httpClient->addHeader('Add-Padding', 'true');
            $this->httpClient->addHeader('User-Agent', 'calmfox-magento-admin-invitation');
            $this->httpClient->get(BreachRange::url($password));

            if (200 !== $this->httpClient->getStatus()) {
                $this->logger->warning('The password breach check did not answer.', ['status' => $this->httpClient->getStatus()]);

                return false;
            }

            return BreachRange::contains((string) $this->httpClient->getBody(), $password);
        } catch (\Throwable $exception) {
            $this->logger->warning('The password breach check could not be performed.', ['exception' => $exception]);

            return false;
        }
    }
}
