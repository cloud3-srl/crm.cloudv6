<?php

namespace Espo\Modules\Cloud3\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class CaseItem extends \Espo\Core\AclPortal\Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        if ($entity->has('caseId')) {
            $caseId = $entity->get('caseId');
            if (!$caseId) return;

            $case = $this->getEntityManager()->getEntity('Case', $caseId);
            if ($case && $this->getAclManager()->getImplementation('Case')->checkInAccount($user, $case)) {
                return true;
            }
        } else {
            return parent::checkInAccount($user, $entity);
        }
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        if ($entity->has('caseId')) {
            $caseId = $entity->get('caseId');
            if (!$caseId) return;

            $case = $this->getEntityManager()->getEntity('Case', $caseId);
            if ($case && $this->getAclManager()->getImplementation('Case')->checkIsOwnContact($user, $case)) {
                return true;
            }
        } else {
            return parent::checkIsOwnContact($user, $entity);
        }
    }
}
