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

class QuoteItem extends \Espo\Core\AclPortal\Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        if ($entity->has('quoteId')) {
            $quoteId = $entity->get('quoteId');
            if (!$quoteId) return;

            $quote = $this->getEntityManager()->getEntity('Quote', $quoteId);
            if ($quote && $this->getAclManager()->getImplementation('Quote')->checkInAccount($user, $quote)) {
                return true;
            }
        } else {
            return parent::checkInAccount($user, $entity);
        }
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        if ($entity->has('quoteId')) {
            $quoteId = $entity->get('quoteId');
            if (!$quoteId) return;

            $quote = $this->getEntityManager()->getEntity('Quote', $quoteId);
            if ($quote && $this->getAclManager()->getImplementation('Quote')->checkIsOwnContact($user, $quote)) {
                return true;
            }
        } else {
            return parent::checkIsOwnContact($user, $entity);
        }
    }
}
