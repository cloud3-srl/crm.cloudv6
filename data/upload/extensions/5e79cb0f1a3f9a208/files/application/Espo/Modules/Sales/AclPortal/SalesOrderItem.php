<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Sales Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/sales-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2020 Letrium Ltd.
 * 
 * License ID: 2f687f2013bc552b8556948039df639e
 ***********************************************************************************/

namespace Espo\Modules\Sales\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class SalesOrderItem extends \Espo\Core\AclPortal\Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        if ($entity->has('salesOrderId')) {
            $salesOrderId = $entity->get('salesOrderId');
            if (!$salesOrderId) return;

            $salesOrder = $this->getEntityManager()->getEntity('SalesOrder', $salesOrderId);
            if ($salesOrder && $this->getAclManager()->getImplementation('SalesOrder')->checkInAccount($user, $salesOrder)) {
                return true;
            }
        } else {
            return parent::checkInAccount($user, $entity);
        }
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        if ($entity->has('salesOrderId')) {
            $salesOrderId = $entity->get('salesOrderId');
            if (!$salesOrderId) return;

            $salesOrder = $this->getEntityManager()->getEntity('SalesOrder', $salesOrderId);
            if ($salesOrder && $this->getAclManager()->getImplementation('SalesOrder')->checkIsOwnContact($user, $salesOrder)) {
                return true;
            }
        } else {
            return parent::checkIsOwnContact($user, $entity);
        }
    }
}
