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

use Espo\Modules\Advanced\Core\Bpmn\Utils\Helper;

use Throwable;
use stdClass;

class SubProcess extends CallActivity
{
    public function process()
    {
        if ($this->isMultiInstance()) {
            $this->processMultiInstance();

            return;
        }

        $target = $this->getNewTargetEntity();

        if (!$target) {
            $GLOBALS['log']->info("BPM Sub-Process: Could not get target for sub-process.");

            $this->fail();

            return;
        }

        $flowNode = $this->getFlowNode();
        $variables = $this->getClonedVariables();

        $this->refreshProcess();

        $parentFlowchartData = $this->getProcess()->get('flowchartData') ?? (object) [];

        $createdEntitiesData = clone $this->getCreatedEntitiesData();

        $eData = Helper::getElementsDataFromFlowchartData((object) [
            'list' => $this->getAttributeValue('dataList') ?? [],
        ]);

        $flowchart = $this->getEntityManager()->getEntity('BpmnFlowchart');

        $flowchart->set([
            'targetType' => $target->getEntityType(),
            'data' => (object) [
                'createdEntitiesData' => $parentFlowchartData->createdEntitiesData ?? (object) [],
                'list' => $this->getAttributeValue('dataList') ?? [],
            ],
            'elementsDataHash' => $eData['elementsDataHash'],
            'hasNoneStartEvent' => count($eData['eventStartIdList']) > 0,
            'eventStartIdList'=> $eData['eventStartIdList'],
            'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
            'assignedUserId' => $this->getProcess()->get('assignedUserId'),
            'name' => $this->getAttributeValue('title') ?? 'Sub-Process',
        ]);

        $subProcess = $this->getEntityManager()->createEntity(
            'BpmnProcess',
            [
                'status' => 'Created',
                'targetId' => $target->id,
                'targetType' => $target->getEntityType(),
                'parentProcessId' => $this->getProcess()->id,
                'parentProcessFlowNodeId' => $flowNode->id,
                'assignedUserId' => $this->getProcess()->get('assignedUserId'),
                'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
                'variables' => $variables,
                'createdEntitiesData' => $createdEntitiesData,
                'startElementId' => $this->getSubProcessStartElementId(),
            ],
            [
                'skipCreatedBy' => true,
                'skipModifiedBy' => true,
                'skipStartProcessFlow' => true,
            ]
        );

        $flowNode->set([
            'status' => 'In Process',
        ]);

        $flowNode->setDataItemValue('subProcessId', $subProcess->id);

        $this->getEntityManager()->saveEntity($flowNode);

        try {
            $this->getManager()->startCreatedProcess($subProcess, $flowchart);
        }
        catch (Throwable $e) {
            $GLOBALS['log']->error("BPM Sub-Process: Starting sub-process failure. " . $e->getMessage());

            $this->fail();

            return;
        }
    }

    /**
     * @return ?string
     */
    protected function getSubProcessStartElementId()
    {
        return null;
    }

    protected function generateSubProcessMultiInstance(int $loopCounter, int $x, int $y): stdClass
    {
        return (object) [
            'type' => $this->getAttributeValue('type'),
            'id' => self::generateElementId(),
            'center' => (object) [
                'x' => $x + 125,
                'y' => $y,
            ],
            'dataList' => $this->getAttributeValue('dataList'),
            'returnVariableList' => $this->getAttributeValue('returnVariableList'),
            'isExpanded' => false,
            'target' => $this->getAttributeValue('target'),
            'targetType' => $this->getAttributeValue('targetType'),
            'targetIdExpression' => $this->getAttributeValue('targetIdExpression'),
            'isMultiInstance' => false,
            'triggeredByEvent' => false,
            'isSequential' => false,
            'loopCollectionExpression' => null,
            'text' => (string) $loopCounter,
        ];
    }
}
