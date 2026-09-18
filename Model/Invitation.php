<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model;

use Calmfox\AdminInvitation\Core\Invitation\Token;
use Calmfox\AdminInvitation\Core\Invitation\Validity;
use Magento\Framework\Model\AbstractModel;

/**
 * The state of one administrator's invitation: waiting for its owner (accepted_at is null) or
 * accepted. Keyed by the admin user id; there is at most one per account.
 */
class Invitation extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Invitation::class);
    }

    public function getUserId(): int
    {
        return (int) $this->getData('user_id');
    }

    public function getTokenHash(): string
    {
        return (string) $this->getData('token_hash');
    }

    public function getInvitedBy(): ?int
    {
        $invitedBy = $this->getData('invited_by');

        return null === $invitedBy ? null : (int) $invitedBy;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->dateTime('created_at');
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->dateTime('expires_at');
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->dateTime('accepted_at');
    }

    public function isPending(): bool
    {
        return null === $this->getData('accepted_at');
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        $expiresAt = $this->getExpiresAt();

        return null === $expiresAt || Validity::isExpired($expiresAt, $now);
    }

    /** Pending, not expired and carrying the token from the link. */
    public function acceptsToken(#[\SensitiveParameter] string $token, \DateTimeImmutable $now): bool
    {
        return $this->isPending() && !$this->isExpired($now) && Token::matches($token, $this->getTokenHash());
    }

    /** A fresh link: the previous one stops working. */
    public function renew(#[\SensitiveParameter] string $token, \DateTimeImmutable $expiresAt, ?int $invitedBy): void
    {
        $this->setData('token_hash', Token::hash($token));
        $this->setData('expires_at', self::format($expiresAt));
        $this->setData('accepted_at', null);
        if (null !== $invitedBy) {
            $this->setData('invited_by', $invitedBy);
        }
    }

    public function markAccepted(\DateTimeImmutable $when): void
    {
        $this->setData('accepted_at', self::format($when));
        // the token is spent
        $this->setData('token_hash', Token::hash(Token::generate()));
    }

    public static function format(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function dateTime(string $field): ?\DateTimeImmutable
    {
        $value = $this->getData($field);
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
