<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Invitation;

use Calmfox\AdminInvitation\Model\Invitation;
use Calmfox\AdminInvitation\Model\InvitationFactory;
use Calmfox\AdminInvitation\Model\ResourceModel\Invitation as InvitationResource;
use Calmfox\AdminInvitation\Model\ResourceModel\Invitation\CollectionFactory;

/**
 * Finds and stores invitations. Grid rows ask one by one, so the pending set is loaded once
 * per request and answered from memory.
 */
class Repository
{
    /** @var array<int, Invitation>|null */
    private ?array $pending = null;

    public function __construct(
        private readonly InvitationFactory $invitationFactory,
        private readonly InvitationResource $resource,
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /** The invitation of this account, pending or accepted; null when the account was never invited. */
    public function find(int $userId): ?Invitation
    {
        $invitation = $this->invitationFactory->create();
        $this->resource->load($invitation, $userId);

        return $invitation->getId() ? $invitation : null;
    }

    /** The pending invitation of this account, or null. Cached for the request. */
    public function findPending(int $userId): ?Invitation
    {
        if (null === $this->pending) {
            $this->pending = [];
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('accepted_at', ['null' => true]);
            /** @var Invitation $invitation */
            foreach ($collection as $invitation) {
                $this->pending[$invitation->getUserId()] = $invitation;
            }
        }

        return $this->pending[$userId] ?? null;
    }

    public function isPending(int $userId): bool
    {
        return null !== $this->findPending($userId);
    }

    public function findOrCreate(int $userId): Invitation
    {
        $invitation = $this->find($userId);
        if (null !== $invitation) {
            return $invitation;
        }

        $invitation = $this->invitationFactory->create();
        $invitation->setId($userId);
        $invitation->setData('user_id', $userId);
        $invitation->isObjectNew(true);

        return $invitation;
    }

    public function save(Invitation $invitation): void
    {
        $this->resource->save($invitation);
        $this->pending = null;
    }
}
