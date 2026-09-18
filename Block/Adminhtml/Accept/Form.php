<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Block\Adminhtml\Accept;

use Calmfox\AdminInvitation\Model\Password\PolicyFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

/**
 * Feeds the acceptance form. The name fields come back filled after a validation error; the
 * passwords never do — a password bounced back into the page is a password in a browser cache.
 */
class Form extends Template
{
    protected $_template = 'Calmfox_AdminInvitation::accept/form.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly PolicyFactory $policyFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getUser(): ?\Magento\User\Model\User
    {
        $user = $this->registry->registry('calmfox_invitation_user');

        return $user instanceof \Magento\User\Model\User ? $user : null;
    }

    public function getEmail(): string
    {
        return (string) ($this->getUser()?->getEmail() ?? '');
    }

    /** The local part of the address, as a starting point the invitee can overwrite. */
    public function getUsername(): string
    {
        $restored = (string) ($this->restored()['username'] ?? '');
        if ('' !== $restored) {
            return $restored;
        }

        $email = $this->getEmail();

        return '' !== $email ? (string) strstr($email, '@', true) : '';
    }

    public function getFirstName(): string
    {
        return (string) ($this->restored()['firstname'] ?? '');
    }

    public function getLastName(): string
    {
        return (string) ($this->restored()['lastname'] ?? '');
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('calmfox_invitation/accept/save', [
            '_nosecret' => true,
            '_query' => [
                'id' => $this->getUser()?->getId(),
                'token' => $this->getRequest()->getParam('token'),
            ],
        ]);
    }

    public function getLoginUrl(): string
    {
        return $this->getUrl('adminhtml', ['_nosecret' => true]);
    }

    /**
     * The step the page opens on: the first one normally, and after a rejected submission the
     * step that owns the problem — identity if one of its fields came back empty, otherwise
     * the password. The messages show above the card either way.
     */
    public function getInitialStep(): int
    {
        $restored = $this->restored();
        if ([] === $restored) {
            return 1;
        }

        foreach (['firstname', 'lastname', 'username'] as $field) {
            if ('' === trim((string) ($restored[$field] ?? ''))) {
                return 1;
            }
        }

        return 2;
    }

    public function getMinLength(): int
    {
        return $this->policyFactory->get()->minLength();
    }

    public function getGeneratedLength(): int
    {
        return $this->policyFactory->get()->generatedLength();
    }

    /** @return array<string, mixed> */
    private function restored(): array
    {
        $form = $this->registry->registry('calmfox_invitation_form');

        return \is_array($form) ? $form : [];
    }
}
