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

namespace Espo\Modules\Advanced\Core\Workflow\Formula\Functions\WorkflowGroup;

use stdClass;

use Espo\Core\Exceptions\Error;

use Espo\Core\Di\InjectableFactoryAware;
use Espo\Core\Di\InjectableFactorySetter;

use Espo\Core\Di\EntityManagerAware;
use Espo\Core\Di\EntityManagerSetter;

use Espo\Modules\Advanced\Services\Workflow as Service;

class TriggerType extends \Espo\Core\Formula\Functions\Base implements

    InjectableFactoryAware,
    EntityManagerAware
{
    use InjectableFactorySetter;
    use EntityManagerSetter;

    public function process(stdClass $item)
    {
        $args = $this->fetchArguments($item);

        $entityType = $args[0] ?? null;
        $id = $args[1] ?? null;
        $workflowId = $args[2] ?? null;

        if (!$entityType) {
            throw new Error("No entity type.");
        }

        if (!$id) {
            throw new Error("No ID.");
        }

        if (!$workflowId) {
            throw new Error("No workflowId.");
        }

        $entity = $this->entityManager->getEntity($entityType, $id);

        if (!$entity) {
            throw new Error("Entity not found.");
        }

        $this->getWorkflowService()->triggerWorkflow($entity, $workflowId);
    }

    private function getWorkflowService(): Service
    {
        return $this->injectableFactory->create(Service::class);
    }
}
