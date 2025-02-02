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

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

abstract class Event extends Base
{
    protected function rejectConcurrentPendingFlows()
    {
        $flowNode = $this->getFlowNode();

        if ($flowNode->get('previousFlowNodeElementType') === 'gatewayEventBased') {
            $concurrentFlowNodeList = $this->getEntityManager()
                ->getRepository('BpmnFlowNode')
                ->where([
                    'previousFlowNodeElementType' => 'gatewayEventBased',
                    'previousFlowNodeId' => $flowNode->get('previousFlowNodeId'),
                    'processId' => $flowNode->get('processId'),
                    'id!=' => $flowNode->id,
                    'status' => 'Pending'
                ])
                ->find();

            foreach ($concurrentFlowNodeList as $concurrentFlowNode) {
                $concurrentFlowNode->set('status', 'Rejected');

                $this->getEntityManager()->saveEntity($concurrentFlowNode);
            }
        }
    }
}
