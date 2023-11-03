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

namespace Espo\Modules\Advanced\Classes\FieldProcessing\BpmnFlowNode;

use Espo\ORM\Entity;

use Espo\Core\{
    FieldProcessing\Loader,
    FieldProcessing\Loader\Params,
    ORM\EntityManager,
};

use Espo\Entities\User;

class UserTaskLoader implements Loader
{
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->get('elementType') !== 'taskUser') {
            return;
        }

        $userTask = $this->entityManager
            ->getRepository('BpmnUserTask')
            ->where([
                'flowNodeId' => $entity->id
            ])
            ->findOne();

        if ($userTask) {
            $entity->set('userTaskId', $userTask->id);
        }
    }
}
