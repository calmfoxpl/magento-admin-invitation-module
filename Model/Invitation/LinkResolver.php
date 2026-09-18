<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Invitation;

use Calmfox\AdminInvitation\Core\Invitation\Token;
use Calmfox\AdminInvitation\Model\Clock;
use Calmfox\AdminInvitation\Model\Invitation;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/** Turns the id and token of a link into the account and invitation behind it, or nothing. */
class LinkResolver
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly Repository $invitations,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{user: User, invitation: Invitation}|null */
    public function resolve(int $userId, #[\SensitiveParameter] string $token): ?array
    {
        if ($userId <= 0 || !Token::isWellFormed($token)) {
            return null;
        }

        $invitation = $this->invitations->find($userId);
        if (null === $invitation || !$invitation->acceptsToken($token, $this->clock->now())) {
            return null;
        }

        $user = $this->userFactory->create();
        $this->userResource->load($user, $userId);
        if (!$user->getId()) {
            return null;
        }

        return ['user' => $user, 'invitation' => $invitation];
    }
}
