<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Invitation extends AbstractDb
{
    /** The primary key is the admin user id, assigned by us, not by the database. */
    protected $_isPkAutoIncrement = false;

    protected $_useIsObjectNew = true;

    protected function _construct(): void
    {
        $this->_init('calmfox_admin_invitation', 'user_id');
    }
}
