<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Block\Adminhtml\User\Edit\Tab;

use Calmfox\AdminInvitation\Model\Invitation\Repository;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Registry;

/**
 * The "Account Access" tab on an administrator's page. It shows whether the account is still
 * waiting for its invitation or is active, and offers one button: resend the invitation, or
 * send a password reset link. The button posts its own small form, so it never interferes with
 * the user form's own Save.
 */
class AccountAccess extends Template implements TabInterface
{
    protected $_template = 'Calmfox_AdminInvitation::user/edit/tab/account_access.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Repository $invitations,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getUser(): ?\Magento\User\Model\User
    {
        $user = $this->registry->registry('permissions_user');

        return $user instanceof \Magento\User\Model\User && $user->getId() ? $user : null;
    }

    public function isPending(): bool
    {
        $user = $this->getUser();

        return null !== $user && $this->invitations->isPending((int) $user->getId());
    }

    public function getAccessLinkUrl(): string
    {
        $user = $this->getUser();

        return $this->getUrl('calmfox_invitation/user/accessLink', ['user_id' => $user?->getId()]);
    }

    public function getConfirmMessage(): string
    {
        $user = $this->getUser();
        $email = $user ? (string) $user->getEmail() : '';

        return $this->isPending()
            ? (string) __('Send the invitation to %1 again?', $email)
            : (string) __('Send a password reset link to %1?', $email);
    }

    public function getTabLabel()
    {
        return __('Account Access');
    }

    public function getTabTitle()
    {
        return __('Account Access');
    }

    public function canShowTab()
    {
        return null !== $this->getUser();
    }

    public function isHidden()
    {
        return false;
    }
}
