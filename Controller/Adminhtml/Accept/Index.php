<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Controller\Adminhtml\Accept;

use Calmfox\AdminInvitation\Model\Invitation\LinkResolver;
use Calmfox\AdminInvitation\Model\Link;
use Calmfox\AdminInvitation\Model\LocaleSwitcher;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Registry;
use Magento\Framework\View\DesignLoader;
use Magento\Framework\View\Result\PageFactory;

/**
 * The page behind the link in the invitation e-mail, where the invitee chooses their name and
 * password.
 *
 * It is reachable without a session on purpose, and that is why it does not extend Magento's
 * backend action: the plugin that sends every logged-out admin request to the sign-in screen
 * watches that class, and its list of open pages cannot be extended. Implementing the action
 * interfaces directly keeps the page public the honest way, at the price of doing by hand the
 * two things the backend action would have done — loading the admin design and choosing the
 * interface language. The link's token is the credential; LinkResolver rejects anything
 * unknown, spent or expired.
 */
class Index implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LinkResolver $linkResolver,
        private readonly DesignLoader $designLoader,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly BackendSession $backendSession,
        private readonly Registry $registry,
        private readonly Link $link,
    ) {
    }

    public function execute()
    {
        $this->designLoader->load();

        $userId = (int) $this->request->getParam('id');
        $token = (string) $this->request->getParam('token');

        $resolved = $this->linkResolver->resolve($userId, $token);
        if (null === $resolved) {
            $this->messageManager->addErrorMessage(
                __('This invitation link has expired or has already been used. Ask an administrator to invite you again.'),
            );

            return $this->redirectFactory->create()->setUrl($this->link->login());
        }

        $user = $resolved['user'];
        $this->localeSwitcher->switchTo($user->getInterfaceLocale());

        $this->registry->register('calmfox_invitation_user', $user, true);
        // whatever was typed before a validation error, minus the passwords
        $this->registry->register('calmfox_invitation_form', (array) $this->backendSession->getCalmfoxAcceptForm(true), true);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Set up your account'));

        return $page;
    }

    /**
     * The page answers the same way to everyone, signed in or not.
     *
     * Without this, an administrator who happens to be signed in and opens someone else's
     * invitation link is stopped by the panel's secret-key check and told the form key is
     * invalid — a confusing answer to "this link is not yours". Reading the page changes
     * nothing, and the token in the link is what decides whether there is anything to show.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
