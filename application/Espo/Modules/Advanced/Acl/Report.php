<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: a3ea4219cf9c3e5dee57026de28a15c1
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Acl;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class Report extends \Espo\Core\Acl\Base
{
    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $entityType = $entity->get('entityType');
        if ($entityType) {
            if (!$this->getAclManager()->checkScope($user, $entityType)) {
                return false;
            }
        }

        return $this->checkEntity($user, $entity, $data, 'read');
    }
}
