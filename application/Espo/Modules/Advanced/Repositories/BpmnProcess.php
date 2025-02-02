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

namespace Espo\Modules\Advanced\Repositories;

use \Espo\ORM\Entity;

class BpmnProcess extends \Espo\Core\ORM\Repositories\RDB
{
    protected function afterRemove(Entity $entity, array $options = array())
    {
        parent::afterRemove($entity, $options);

        $flowNodeList = $this->getEntityManager()->getRepository('BpmnFlowNode')->where([
            'processId' => $entity->id,
            'status!=' => ['Processed', 'Rejected', 'Failed']
        ])->find();

        foreach ($flowNodeList as $flowNode) {
            $flowNode->set('status', 'Rejected');
            $this->getEntityManager()->saveEntity($flowNode);
        }
    }
}
