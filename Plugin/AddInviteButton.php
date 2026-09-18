<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Plugin;

use Magento\Framework\View\LayoutInterface;
use Magento\User\Block\User as UsersGridContainer;

/**
 * "Invite Administrator" next to "Add New User" on System > Permissions > All Users. Buttons
 * are collected when the block receives its layout, hence the before plugin on setLayout().
 */
class AddInviteButton
{
    public function beforeSetLayout(UsersGridContainer $subject, LayoutInterface $layout): array
    {
        $subject->addButton(
            'calmfox_invite',
            [
                'label' => __('Invite Administrator'),
                'class' => 'invite',
                'onclick' => "setLocation('" . $subject->escapeJs($subject->getUrl('calmfox_invitation/user/invite')) . "')",
            ],
            0,
            -10,
        );

        return [$layout];
    }
}
