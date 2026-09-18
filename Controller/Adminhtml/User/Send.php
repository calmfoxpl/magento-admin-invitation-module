<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Controller\Adminhtml\User;

use Calmfox\AdminInvitation\Model\Invitation\Inviter;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\Validator\Locale as LocaleValidator;
use Psr\Log\LoggerInterface;

/** The invite form submitted. */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_User::acl_users';

    public function __construct(
        Context $context,
        private readonly Inviter $inviter,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly EmailAddress $emailValidator,
        private readonly LocaleValidator $localeValidator,
        private readonly BackendSession $backendSession,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $email = trim((string) $this->getRequest()->getParam('email'));
        $roleId = (int) $this->getRequest()->getParam('role_id');
        $locale = (string) $this->getRequest()->getParam('interface_locale');
        $redirect = $this->resultRedirectFactory->create();

        $errors = [];
        if (!$this->emailValidator->isValid($email)) {
            $errors[] = __('Please enter a valid e-mail address.');
        }
        if (!$this->roleExists($roleId)) {
            $errors[] = __('Please choose a role for the new administrator.');
        }
        if (!$this->localeValidator->isValid($locale)) {
            $locale = (string) $this->_auth->getUser()?->getInterfaceLocale();
        }

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $this->messageManager->addErrorMessage($error);
            }

            return $this->backToForm($redirect, $email, $roleId, $locale);
        }

        try {
            $this->inviter->invite($email, $roleId, $locale, $this->currentUserId());
        } catch (MailException $exception) {
            // the account exists; the panel says how to send the invitation again
            $this->logger->error('The administrator invitation could not be sent.', ['email' => $email, 'exception' => $exception]);
            $this->messageManager->addErrorMessage(__(
                'The account for %1 was created, but the invitation could not be sent. Check the mail configuration and send it again from the user\'s page or with: bin/magento calmfox:admin:invite %1',
                $email,
            ));

            return $redirect->setPath('adminhtml/user/');
        } catch (AlreadyExistsException|LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->backToForm($redirect, $email, $roleId, $locale);
        }

        $this->messageManager->addSuccessMessage(__('The invitation has been sent to %1.', $email));

        return $redirect->setPath('adminhtml/user/');
    }

    private function backToForm(\Magento\Framework\Controller\Result\Redirect $redirect, string $email, int $roleId, string $locale): \Magento\Framework\Controller\Result\Redirect
    {
        $this->backendSession->setCalmfoxInviteForm(['email' => $email, 'role_id' => $roleId, 'interface_locale' => $locale]);

        return $redirect->setPath('calmfox_invitation/user/invite');
    }

    private function roleExists(int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }
        $roles = $this->roleCollectionFactory->create()->setRolesFilter();
        $roles->addFieldToFilter('role_id', $roleId);

        return $roles->getSize() > 0;
    }

    private function currentUserId(): ?int
    {
        $user = $this->_auth->getUser();

        return $user && $user->getId() ? (int) $user->getId() : null;
    }
}
