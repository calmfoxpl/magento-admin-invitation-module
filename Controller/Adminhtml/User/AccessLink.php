<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Controller\Adminhtml\User;

use Calmfox\AdminInvitation\Model\Invitation\Inviter;
use Calmfox\AdminInvitation\Model\Invitation\PasswordResetLinkSender;
use Calmfox\AdminInvitation\Model\Invitation\Repository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\MailException;
use Magento\User\Model\UserFactory;
use Magento\User\Model\ResourceModel\User as UserResource;
use Psr\Log\LoggerInterface;

/**
 * One button, "let this person back in": an account still waiting for its invitation gets the
 * invitation again; an active one gets a link to set a new password themselves.
 */
class AccessLink extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_User::acl_users';

    public function __construct(
        Context $context,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly Repository $invitations,
        private readonly Inviter $inviter,
        private readonly PasswordResetLinkSender $passwordResetLinkSender,
        private readonly AuthSession $authSession,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $userId = (int) $this->getRequest()->getParam('user_id');

        $user = $this->userFactory->create();
        $this->userResource->load($user, $userId);
        if (!$user->getId()) {
            $this->messageManager->addErrorMessage(__('This administrator no longer exists.'));

            return $resultRedirect->setPath('adminhtml/user/index');
        }

        $email = (string) $user->getEmail();
        $pending = $this->invitations->isPending($userId);

        try {
            if ($pending) {
                $currentUser = $this->authSession->getUser();
                $this->inviter->resend($user, $currentUser ? (int) $currentUser->getId() : null);
                $this->messageManager->addSuccessMessage(__('The invitation has been sent to %1 again.', $email));
            } else {
                $this->passwordResetLinkSender->send($user);
                $this->messageManager->addSuccessMessage(__('A password reset link has been sent to %1.', $email));
            }
        } catch (MailException $exception) {
            $this->logger->error('The administrator access link could not be sent.', ['email' => $email, 'exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The e-mail to %1 could not be sent. Check the mail settings.', $email));
        } catch (\Throwable $exception) {
            $this->logger->error('The administrator access link failed.', ['email' => $email, 'exception' => $exception]);
            $this->messageManager->addErrorMessage(__('Something went wrong while sending the access link.'));
        }

        return $resultRedirect->setPath('adminhtml/user/edit', ['user_id' => $userId]);
    }
}
