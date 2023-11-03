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

class OpportunityItem extends \Espo\Core\AclPortal\Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        if ($entity->has('opportunityId')) {
            $opportunityId = $entity->get('opportunityId');
            if (!$opportunityId) return;

            $opportunity = $this->getEntityManager()->getEntity('Opportunity', $opportunityId);
            if ($opportunity && $this->getAclManager()->getImplementation('Opportunity')->checkInAccount($user, $opportunity)) {
                return true;
            }
        } else {
            return parent::checkInAccount($user, $entity);
        }
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        if ($entity->has('opportunityId')) {
            $opportunityId = $entity->get('opportunityId');
            if (!$opportunityId) return;

            $opportunity = $this->getEntityManager()->getEntity('Opportunity', $opportunityId);
            if ($opportunity && $this->getAclManager()->getImplementation('Opportunity')->checkIsOwnContact($user, $opportunity)) {
                return true;
            }
        } else {
            return parent::checkIsOwnContact($user, $entity);
        }
    }
}
