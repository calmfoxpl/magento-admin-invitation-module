<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Controller\Adminhtml\User;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

/**
 * The invite form: an e-mail address, a role and a language. An ordinary panel page, guarded
 * by the same permission as creating a user — whoever may add an administrator may invite one.
 */
class Invite extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_User::acl_users';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly BackendSession $backendSession,
        private readonly Registry $registry,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        // what was typed before a validation error sent the form back
        $this->registry->register('calmfox_invitation_form', (array) $this->backendSession->getCalmfoxInviteForm(true), true);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Magento_User::system_acl_users');
        $page->addBreadcrumb(__('Users'), __('Users'), $this->getUrl('adminhtml/user/'));
        $page->addBreadcrumb(__('Invite Administrator'), __('Invite Administrator'));
        $page->getConfig()->getTitle()->prepend(__('Users'));
        $page->getConfig()->getTitle()->prepend(__('Invite Administrator'));

        return $page;
    }
}
