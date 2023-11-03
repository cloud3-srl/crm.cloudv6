<?php

namespace Espo\Modules\Cloud3\Acl;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class CaseItem extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(User $user, Entity $entity)
    {
        if ($entity->has('caseId')) {
            $caseId = $entity->get('caseId');
            if (!$caseId) return;

            $case = $this->getEntityManager()->getEntity('Case', $caseId);
            if ($case && $this->getAclManager()->getImplementation('Case')->checkIsOwner($user, $case)) {
                return true;
            }
        } else {
            return parent::checkIsOwner($user, $entity);
        }
    }

    public function checkInTeam(User $user, Entity $entity)
    {
        if ($entity->has('caseId')) {
            $caseId = $entity->get('caseId');
            if (!$caseId) return;

            $case = $this->getEntityManager()->getEntity('Case', $caseId);
            if ($case && $this->getAclManager()->getImplementation('Case')->checkInTeam($user, $case)) {
                return true;
            }
        } else {
            return parent::checkInTeam($user, $entity);
        }
    }
}
