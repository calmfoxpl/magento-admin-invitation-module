<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Controller\Adminhtml\Accept;

use Calmfox\AdminInvitation\Model\Invitation\Acceptor;
use Calmfox\AdminInvitation\Model\Invitation\LinkResolver;
use Calmfox\AdminInvitation\Model\Link;
use Calmfox\AdminInvitation\Model\LocaleSwitcher;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Phrase;
use Magento\Framework\View\DesignLoader;
use Psr\Log\LoggerInterface;

/**
 * The acceptance form submitted: the account gets its owner's name and password, is enabled,
 * and the new administrator lands in the panel already signed in.
 *
 * Dispatches `calmfox_admin_invitation_accepted` with the user and a `redirect` object whose
 * url other modules may change, e.g. to send the new administrator to two-factor setup.
 */
class Save implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const EVENT_ACCEPTED = 'calmfox_admin_invitation_accepted';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LinkResolver $linkResolver,
        private readonly DesignLoader $designLoader,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly Acceptor $acceptor,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly BackendSession $backendSession,
        private readonly BackendUrl $backendUrl,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger,
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
            $this->messageManager->addErrorMessage(__('This invitation link has expired or has already been used. Ask an administrator to invite you again.'));

            return $this->redirectFactory->create()->setUrl($this->link->login());
        }

        $user = $resolved['user'];
        $this->localeSwitcher->switchTo($user->getInterfaceLocale());

        $data = [
            'username' => trim((string) $this->request->getParam('username')),
            'firstname' => trim((string) $this->request->getParam('firstname')),
            'lastname' => trim((string) $this->request->getParam('lastname')),
            'password' => (string) $this->request->getParam('password'),
            'confirmation' => (string) $this->request->getParam('confirmation'),
        ];

        $errors = $this->missingFields($data);
        if ([] === $errors) {
            try {
                $this->acceptor->accept($user, $resolved['invitation'], $data);
            } catch (LocalizedException $exception) {
                $errors = array_filter(array_map('trim', explode(PHP_EOL, $exception->getMessage())));
            } catch (\Throwable $exception) {
                $this->logger->error('The administrator invitation could not be accepted.', ['user_id' => $userId, 'exception' => $exception]);
                $errors = [__('Something went wrong while saving your account. Please try again.')];
            }
        }

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $this->messageManager->addErrorMessage($error instanceof Phrase ? $error : __($error));
            }
            // the passwords are never sent back to the browser; the names are, so the invitee
            // does not retype them, and they also tell the page which step to reopen on
            $this->backendSession->setCalmfoxAcceptForm(array_diff_key($data, ['password' => 1, 'confirmation' => 1]));

            return $this->redirectFactory->create()->setUrl($this->link->acceptInvitation($userId, $token));
        }

        $this->messageManager->addSuccessMessage(__('Welcome! Your account is ready.'));

        $redirect = new DataObject(['url' => $this->backendUrl->getUrl($this->backendUrl->getStartupPageUrl())]);
        $this->eventManager->dispatch(self::EVENT_ACCEPTED, ['user' => $user, 'redirect' => $redirect]);

        return $this->redirectFactory->create()->setUrl((string) $redirect->getData('url'));
    }

    /**
     * @param array<string, string> $data
     *
     * @return list<Phrase>
     */
    private function missingFields(array $data): array
    {
        $errors = [];
        if ('' === $data['username']) {
            $errors[] = __('Please enter a user name.');
        }
        if ('' === $data['firstname']) {
            $errors[] = __('Please enter your first name.');
        }
        if ('' === $data['lastname']) {
            $errors[] = __('Please enter your last name.');
        }
        if ('' === $data['password']) {
            $errors[] = __('Please enter a password.');
        } elseif ($data['password'] !== $data['confirmation']) {
            $errors[] = __('The passwords do not match.');
        }

        return $errors;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $this->messageManager->addErrorMessage(__('Invalid Form Key. Please refresh the page.'));

        return new InvalidRequestException(
            $this->redirectFactory->create()->setUrl($this->link->acceptInvitation(
                (int) $request->getParam('id'),
                (string) $request->getParam('token'),
            )),
        );
    }
}
