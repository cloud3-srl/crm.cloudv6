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
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

namespace Espo\Modules\Sales\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class InvoiceItem extends \Espo\Core\AclPortal\Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        if ($entity->has('invoiceId')) {
            $invoiceId = $entity->get('invoiceId');
            if (!$invoiceId) return false;

            $invoice = $this->getEntityManager()->getEntity('Invoice', $invoiceId);
            if ($invoice && $this->getAclManager()->getImplementation('Invoice')->checkInAccount($user, $invoice)) {
                return true;
            }
        } else {
            return parent::checkInAccount($user, $entity);
        }

        return false;
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        if ($entity->has('invoiceId')) {
            $invoiceId = $entity->get('invoiceId');
            if (!$invoiceId) return false;

            $invoice = $this->getEntityManager()->getEntity('Invoice', $invoiceId);
            if ($invoice && $this->getAclManager()->getImplementation('Invoice')->checkIsOwnContact($user, $invoice)) {
                return true;
            }
        } else {
            return parent::checkIsOwnContact($user, $entity);
        }

        return false;
    }
}
