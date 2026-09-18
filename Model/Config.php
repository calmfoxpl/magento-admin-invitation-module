<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/** Typed access to Stores > Configuration > Security > Administrator Invitations. */
class Config
{
    public const XML_PATH = 'calmfox_admin_invitation/';

    /** Magento's own "Recovery Link Expiration Period" for admin password reset links, in hours. */
    public const XML_PATH_RESET_LINK_HOURS = 'admin/security/password_reset_link_expiration_period';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function invitationDays(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH . 'invitation/ttl_days'));
    }

    public function passwordResetHours(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_RESET_LINK_HOURS));
    }

    public function passwordMinLength(): int
    {
        return max(8, (int) $this->scopeConfig->getValue(self::XML_PATH . 'password/min_length'));
    }

    public function passwordMinStrength(): int
    {
        return min(4, max(1, (int) $this->scopeConfig->getValue(self::XML_PATH . 'password/min_strength')));
    }

    public function passwordNotCompromised(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . 'password/not_compromised');
    }

    public function generatedPasswordLength(): int
    {
        return min(64, max(12, (int) $this->scopeConfig->getValue(self::XML_PATH . 'password/generated_length')));
    }

    public function policyAppliesEverywhere(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . 'password/apply_everywhere');
    }

    public function emailIdentity(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH . 'emails/identity') ?: 'general');
    }

    public function invitationTemplate(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH . 'emails/invitation_template');
    }

    public function passwordResetTemplate(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH . 'emails/password_reset_template');
    }
}
