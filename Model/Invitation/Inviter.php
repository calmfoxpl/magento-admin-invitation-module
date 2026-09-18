<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Invitation;

use Calmfox\AdminInvitation\Core\Invitation\Token;
use Calmfox\AdminInvitation\Core\Invitation\Validity;
use Calmfox\AdminInvitation\Model\Clock;
use Calmfox\AdminInvitation\Model\Config;
use Calmfox\AdminInvitation\Model\Link;
use Calmfox\AdminInvitation\Model\Mail\Mailer;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/**
 * Creates the account behind an invitation and sends the link. The account is created
 * inactive, with a random password nobody knows and with the e-mail as user name; the invitee
 * fills in the rest on the acceptance page. Magento's validation demands a first and last name
 * on every save, so until then the account carries the e-mail address as its name.
 */
class Inviter
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly Repository $invitations,
        private readonly Mailer $mailer,
        private readonly Link $link,
        private readonly Config $config,
        private readonly Clock $clock,
        private readonly Random $random,
    ) {
    }

    /**
     * A new account for this address, invited. The e-mail failure is reported after the
     * account exists: the caller tells how to resend.
     *
     * @throws AlreadyExistsException when the address or user name is taken
     * @throws LocalizedException on other validation errors
     * @throws \Magento\Framework\Exception\MailException when the account was created but the e-mail failed
     */
    public function invite(string $email, int $roleId, string $interfaceLocale, ?int $invitedBy): User
    {
        $email = trim($email);
        if ($this->emailExists($email)) {
            throw new AlreadyExistsException(__('An administrator with the e-mail address %1 already exists.', $email));
        }

        $user = $this->userFactory->create();
        $user->setEmail($email);
        $user->setUserName($email);
        $user->setFirstName(self::placeholderName($email, 0));
        $user->setLastName(self::placeholderName($email, 1));
        $user->setPassword($this->unusablePassword());
        $user->setIsActive(0);
        $user->setInterfaceLocale($interfaceLocale);
        $user->setRoleId($roleId);

        try {
            $this->userResource->save($user);
        } catch (\Magento\Framework\Validator\Exception $exception) {
            throw new LocalizedException(__($exception->getMessage()), $exception);
        }

        $this->send($user, $invitedBy);

        return $user;
    }

    /**
     * The invitation again, with a fresh link. Only for accounts that have not accepted yet.
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function resend(User $user, ?int $invitedBy): void
    {
        if (!$this->invitations->isPending((int) $user->getId())) {
            throw new LocalizedException(__('%1 already has an active account.', (string) $user->getEmail()));
        }

        $this->send($user, $invitedBy);
    }

    private function send(User $user, ?int $invitedBy): void
    {
        $token = Token::generate();
        $expiresAt = Validity::invitationExpiry($this->clock->now(), $this->config->invitationDays());

        $invitation = $this->invitations->findOrCreate((int) $user->getId());
        $invitation->renew($token, $expiresAt, $invitedBy);
        $this->invitations->save($invitation);

        $this->mailer->sendInvitation($user, $this->link->acceptInvitation((int) $user->getId(), $token), $expiresAt);
    }

    private function emailExists(string $email): bool
    {
        $user = $this->userFactory->create();
        $this->userResource->load($user, $email, 'email');

        return (bool) $user->getId();
    }

    /** Long, random, and it satisfies Magento's letters-and-digits rule. Never shown to anyone. */
    private function unusablePassword(): string
    {
        return $this->random->getRandomString(40) . 'Aa1';
    }

    /** The local part of the address until the invitee gives their name; 32 characters fit the column. */
    private static function placeholderName(string $email, int $part): string
    {
        $local = (string) strstr($email, '@', true);
        if ('' === $local) {
            $local = $email;
        }
        $pieces = preg_split('/[._\-+]+/', $local, 2) ?: [$local];
        $name = $pieces[$part] ?? ($part === 0 ? $local : '-');

        return mb_substr('' !== $name ? $name : '-', 0, 32);
    }
}
