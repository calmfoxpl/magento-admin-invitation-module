<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Invitation;

use Calmfox\AdminInvitation\Core\Invitation\Validity;
use Calmfox\AdminInvitation\Model\Clock;
use Calmfox\AdminInvitation\Model\Config;
use Calmfox\AdminInvitation\Model\Link;
use Calmfox\AdminInvitation\Model\Mail\Mailer;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;

/**
 * Another administrator sends a link with which this one sets a new password themselves:
 * nobody learns or dictates it. The link opens Magento's own reset page and uses Magento's
 * own token and expiration period; only the e-mail (and, with the policy applied everywhere,
 * the password rules on that page) come from the module.
 */
class PasswordResetLinkSender
{
    public function __construct(
        private readonly UserResource $userResource,
        private readonly BackendHelper $backendHelper,
        private readonly Mailer $mailer,
        private readonly Link $link,
        private readonly Config $config,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function send(User $user): void
    {
        $token = $this->backendHelper->generateResetPasswordLinkToken();
        $user->changeResetPasswordLinkToken($token);
        $this->userResource->save($user);

        $validUntil = Validity::passwordResetExpiry($this->clock->now(), $this->config->passwordResetHours());
        $this->mailer->sendPasswordResetLink($user, $this->link->resetPassword((int) $user->getId(), $token), $validUntil);
    }
}
