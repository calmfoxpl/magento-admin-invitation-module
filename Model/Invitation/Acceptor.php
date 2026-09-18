<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Invitation;

use Calmfox\AdminInvitation\Model\Clock;
use Calmfox\AdminInvitation\Model\Invitation;
use Calmfox\AdminInvitation\Model\Password\PolicyScope;
use Magento\Backend\Model\Auth;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;

/**
 * The invitee's side: name, user name and password go on the account, the account is enabled,
 * the invitation is spent and the new administrator is logged in through Magento's own
 * authentication, so login observers (lockouts, password history, notifications) run as usual.
 */
class Acceptor
{
    public function __construct(
        private readonly UserResource $userResource,
        private readonly Repository $invitations,
        private readonly PolicyScope $policyScope,
        private readonly Auth $auth,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array{username: string, firstname: string, lastname: string, password: string, confirmation: string} $data
     *
     * @throws LocalizedException with the messages to show
     */
    public function accept(User $user, Invitation $invitation, #[\SensitiveParameter] array $data): void
    {
        $user->setUserName(trim($data['username']));
        $user->setFirstName(trim($data['firstname']));
        $user->setLastName(trim($data['lastname']));
        $user->setPassword($data['password']);
        $user->setPasswordConfirmation($data['confirmation']);
        $user->setIsActive(1);
        // a stale "Forgot your password?" link must not open the account after this
        $user->setRpToken(null);
        $user->setRpTokenCreatedAt(null);

        try {
            $this->policyScope->enforce(function () use ($user): void {
                $this->userResource->save($user);
            });
        } catch (\Magento\Framework\Validator\Exception $exception) {
            throw new LocalizedException(__($exception->getMessage()), $exception);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $exception) {
            throw new LocalizedException(__('A user with the same user name or e-mail address already exists.'), $exception);
        }

        $invitation->markAccepted($this->clock->now());
        $this->invitations->save($invitation);

        $this->auth->login((string) $user->getUserName(), $data['password']);
    }
}
