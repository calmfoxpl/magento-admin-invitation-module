<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\ResourceModel\Invitation;

use Calmfox\AdminInvitation\Model\Invitation;
use Calmfox\AdminInvitation\Model\ResourceModel\Invitation as InvitationResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'user_id';

    protected function _construct(): void
    {
        $this->_init(Invitation::class, InvitationResource::class);
    }
}
