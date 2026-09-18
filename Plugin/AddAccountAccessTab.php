<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Plugin;

use Calmfox\AdminInvitation\Block\Adminhtml\User\Edit\Tab\AccountAccess;
use Magento\Framework\Registry;
use Magento\User\Block\User\Edit\Tabs;

/** The "Account Access" tab on an existing administrator's page, after "User Role". */
class AddAccountAccessTab
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function beforeToHtml(Tabs $subject): void
    {
        $user = $this->registry->registry('permissions_user');
        if (!\is_object($user) || !method_exists($user, 'getId') || !$user->getId()) {
            return;
        }

        $subject->addTabAfter(
            'calmfox_account_access',
            [
                'label' => __('Account Access'),
                'title' => __('Account Access'),
                'content' => $subject->getLayout()->createBlock(AccountAccess::class)->toHtml(),
            ],
            'roles_section',
        );
    }
}
