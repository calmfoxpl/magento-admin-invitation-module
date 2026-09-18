<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Block\Adminhtml\User\Invite;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Locale\OptionInterface as LocaleOptions;
use Magento\Framework\Registry;

/** Feeds the invite form: where it posts, the user roles, the languages and what was typed before. */
class Form extends Template
{
    protected $_template = 'Calmfox_AdminInvitation::user/invite/form.phtml';

    public function __construct(
        Context $context,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly LocaleOptions $localeOptions,
        private readonly AuthSession $authSession,
        private readonly Registry $registry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('calmfox_invitation/user/send');
    }

    public function getUsersUrl(): string
    {
        return $this->getUrl('adminhtml/user/');
    }

    /** @return array<int, string> role id => role name, administrator roles only */
    public function getRoleOptions(): array
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->setRolesFilter()->setOrder('role_name', 'ASC');

        $options = [];
        foreach ($collection as $role) {
            $options[(int) $role->getId()] = (string) $role->getRoleName();
        }

        return $options;
    }

    /** @return array<int, array{value: string, label: string}> the locales this installation has deployed */
    public function getLocaleOptions(): array
    {
        return $this->localeOptions->getOptionLocales();
    }

    public function getEmail(): string
    {
        return (string) ($this->restored()['email'] ?? '');
    }

    public function getRoleId(): int
    {
        return (int) ($this->restored()['role_id'] ?? 0);
    }

    /** The invitee's panel opens in the inviter's language until they change it themselves. */
    public function getLocale(): string
    {
        $restored = (string) ($this->restored()['interface_locale'] ?? '');
        if ('' !== $restored) {
            return $restored;
        }

        $user = $this->authSession->getUser();

        return (string) ($user?->getInterfaceLocale() ?: \Magento\Framework\Locale\Resolver::DEFAULT_LOCALE);
    }

    /** @return array<string, mixed> */
    private function restored(): array
    {
        $form = $this->registry->registry('calmfox_invitation_form');

        return \is_array($form) ? $form : [];
    }
}
